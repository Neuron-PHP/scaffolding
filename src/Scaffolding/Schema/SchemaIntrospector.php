<?php

namespace Neuron\Scaffolding\Schema;

use PDO;
use Exception;

/**
 * Introspects an existing database table into a {@see FieldSet}.
 *
 * Supports SQLite (PRAGMA table_info), MySQL and PostgreSQL
 * (information_schema.columns). This is the primary path for generating
 * CRUD against pre-existing tables such as the legacy GroupOffice schema.
 *
 * @package Neuron\Scaffolding\Schema
 */
class SchemaIntrospector
{
	public function __construct( private PDO $pdo )
	{
	}

	/**
	 * Introspect a table and return its FieldSet.
	 *
	 * @param string $table
	 * @return FieldSet
	 * @throws Exception if the adapter is unsupported or the table has no columns
	 */
	public function introspect( string $table ): FieldSet
	{
		$adapter = Connection::adapter( $this->pdo );

		$fields = match( $adapter )
		{
			'sqlite' => $this->introspectSqlite( $table ),
			'mysql'  => $this->introspectMysql( $table ),
			'pgsql'  => $this->introspectPostgres( $table ),
			default  => throw new Exception( "Unsupported adapter for introspection: $adapter" )
		};

		if( empty( $fields ) )
		{
			throw new Exception( "Table '$table' was not found or has no columns." );
		}

		return new FieldSet( $fields );
	}

	/**
	 * @return Field[]
	 */
	private function introspectSqlite( string $table ): array
	{
		$stmt = $this->pdo->prepare( 'PRAGMA table_info(' . $this->quoteIdentifier( $table ) . ')' );
		$stmt->execute();
		$rows = $stmt->fetchAll();

		$fields = [];

		foreach( $rows as $row )
		{
			$rawType = (string)$row[ 'type' ];
			$isPrimary = (int)$row[ 'pk' ] === 1;
			$type = $this->mapSqliteType( $rawType );

			$fields[] = new Field(
				name: $row[ 'name' ],
				type: $type,
				nullable: (int)$row[ 'notnull' ] === 0 && !$isPrimary,
				length: $this->extractLength( $rawType ),
				default: $row[ 'dflt_value' ] ?? null,
				isPrimary: $isPrimary,
				autoIncrement: $isPrimary && $type === 'integer'
			);
		}

		return $fields;
	}

	/**
	 * @return Field[]
	 */
	private function introspectMysql( string $table ): array
	{
		$stmt = $this->pdo->prepare(
			'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA,
			        CHARACTER_MAXIMUM_LENGTH, COLUMN_DEFAULT
			 FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
			 ORDER BY ORDINAL_POSITION'
		);
		$stmt->execute( [ ':table' => $table ] );
		$rows = $stmt->fetchAll();

		$fields = [];

		foreach( $rows as $row )
		{
			$dataType = strtolower( (string)$row[ 'DATA_TYPE' ] );
			$columnType = strtolower( (string)$row[ 'COLUMN_TYPE' ] );

			// MySQL booleans are tinyint(1)
			$type = ( $dataType === 'tinyint' && str_contains( $columnType, 'tinyint(1)' ) )
				? 'boolean'
				: FieldSet::normalizeType( $dataType );

			$isPrimary = $row[ 'COLUMN_KEY' ] === 'PRI';

			$fields[] = new Field(
				name: $row[ 'COLUMN_NAME' ],
				type: $type,
				nullable: $row[ 'IS_NULLABLE' ] === 'YES',
				length: $row[ 'CHARACTER_MAXIMUM_LENGTH' ] !== null ? (int)$row[ 'CHARACTER_MAXIMUM_LENGTH' ] : null,
				default: $row[ 'COLUMN_DEFAULT' ],
				isPrimary: $isPrimary,
				autoIncrement: str_contains( strtolower( (string)$row[ 'EXTRA' ] ), 'auto_increment' )
			);
		}

		return $fields;
	}

	/**
	 * @return Field[]
	 */
	private function introspectPostgres( string $table ): array
	{
		$stmt = $this->pdo->prepare(
			'SELECT column_name, data_type, is_nullable, character_maximum_length,
			        column_default
			 FROM information_schema.columns
			 WHERE table_name = :table
			 ORDER BY ordinal_position'
		);
		$stmt->execute( [ ':table' => $table ] );
		$rows = $stmt->fetchAll();

		$fields = [];

		foreach( $rows as $row )
		{
			$dataType = strtolower( (string)$row[ 'data_type' ] );
			$type = FieldSet::normalizeType( $dataType );
			$default = $row[ 'column_default' ];
			$autoIncrement = $default !== null && str_contains( strtolower( (string)$default ), 'nextval' );
			$isPrimary = $row[ 'column_name' ] === 'id';

			$fields[] = new Field(
				name: $row[ 'column_name' ],
				type: $type,
				nullable: $row[ 'is_nullable' ] === 'YES',
				length: $row[ 'character_maximum_length' ] !== null ? (int)$row[ 'character_maximum_length' ] : null,
				default: $default,
				isPrimary: $isPrimary,
				autoIncrement: $autoIncrement
			);
		}

		return $fields;
	}

	/**
	 * Map a raw SQLite declared type to a logical type.
	 */
	private function mapSqliteType( string $rawType ): string
	{
		$normalized = strtolower( trim( $rawType ) );

		// Strip any length specifier, e.g. varchar(255) -> varchar
		if( ( $pos = strpos( $normalized, '(' ) ) !== false )
		{
			$normalized = substr( $normalized, 0, $pos );
		}

		return match( true )
		{
			str_contains( $normalized, 'int' )       => 'integer',
			str_contains( $normalized, 'char' ),
			str_contains( $normalized, 'clob' ),
			str_contains( $normalized, 'text' )       => $normalized === 'text' ? 'text' : 'string',
			str_contains( $normalized, 'real' ),
			str_contains( $normalized, 'floa' ),
			str_contains( $normalized, 'doub' )       => 'float',
			str_contains( $normalized, 'dec' ),
			str_contains( $normalized, 'num' )         => 'decimal',
			str_contains( $normalized, 'bool' )        => 'boolean',
			$normalized === 'datetime',
			$normalized === 'timestamp'                => 'datetime',
			$normalized === 'date'                     => 'date',
			$normalized === 'time'                     => 'time',
			str_contains( $normalized, 'json' )        => 'json',
			default                                    => 'string'
		};
	}

	/**
	 * Extract a length specifier from a declared type, e.g. varchar(255) -> 255.
	 */
	private function extractLength( string $rawType ): ?int
	{
		if( preg_match( '/\((\d+)\)/', $rawType, $matches ) )
		{
			return (int)$matches[ 1 ];
		}

		return null;
	}

	/**
	 * Quote a table identifier for safe interpolation in PRAGMA.
	 */
	private function quoteIdentifier( string $identifier ): string
	{
		return '"' . str_replace( '"', '""', $identifier ) . '"';
	}
}
