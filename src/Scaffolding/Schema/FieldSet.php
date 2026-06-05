<?php

namespace Neuron\Scaffolding\Schema;

/**
 * An ordered collection of {@see Field} objects describing a resource.
 *
 * A FieldSet can be built from a `--fields` definition string or from
 * database introspection (see {@see SchemaIntrospector}). Generators consume
 * it to produce models, DTOs, repositories, views and migrations.
 *
 * @package Neuron\Scaffolding\Schema
 */
class FieldSet
{
	/**
	 * @param Field[] $fields
	 */
	public function __construct( private array $fields = [] )
	{
	}

	/**
	 * Build a FieldSet from a `--fields` definition string.
	 *
	 * Format: "title:string,body:text,published:boolean,name:string:255".
	 * An optional third segment sets the length for string types.
	 *
	 * @param string $definition
	 * @return self
	 */
	public static function fromDefinition( string $definition ): self
	{
		$fields = [];

		foreach( explode( ',', $definition ) as $part )
		{
			$part = trim( $part );

			if( $part === '' || !str_contains( $part, ':' ) )
			{
				continue;
			}

			$segments = array_map( 'trim', explode( ':', $part ) );
			$name = $segments[ 0 ];
			$type = self::normalizeType( $segments[ 1 ] ?? 'string' );
			$length = isset( $segments[ 2 ] ) && is_numeric( $segments[ 2 ] )
				? (int)$segments[ 2 ]
				: ( in_array( $type, [ 'string', 'email', 'uuid' ], true ) ? 255 : null );

			$fields[] = new Field(
				name: $name,
				type: $type,
				nullable: $type === 'text',
				length: $length
			);
		}

		return new self( $fields );
	}

	/**
	 * Normalize a user-supplied or DB type to a logical type.
	 *
	 * @param string $type
	 * @return string
	 */
	public static function normalizeType( string $type ): string
	{
		$type = strtolower( trim( $type ) );

		return match( $type )
		{
			'string', 'varchar', 'char'            => 'string',
			'text', 'mediumtext', 'longtext', 'tinytext', 'clob' => 'text',
			'int', 'integer', 'smallint', 'mediumint', 'tinyint' => 'integer',
			'bigint', 'biginteger'                 => 'biginteger',
			'float', 'double', 'real'              => 'float',
			'decimal', 'numeric'                   => 'decimal',
			'bool', 'boolean'                      => 'boolean',
			'date'                                 => 'date',
			'datetime', 'timestamp'                => 'datetime',
			'time'                                 => 'time',
			'json', 'jsonb'                        => 'json',
			'email'                                => 'email',
			'uuid'                                 => 'uuid',
			default                                => 'string'
		};
	}

	/**
	 * @return Field[] All fields.
	 */
	public function all(): array
	{
		return $this->fields;
	}

	/**
	 * @return bool Whether the set contains any fields.
	 */
	public function isEmpty(): bool
	{
		return count( $this->fields ) === 0;
	}

	/**
	 * Get the primary key field, if one is defined.
	 *
	 * @return Field|null
	 */
	public function primary(): ?Field
	{
		foreach( $this->fields as $field )
		{
			if( $field->isPrimary )
			{
				return $field;
			}
		}

		return null;
	}

	/**
	 * Fields that should appear in forms and DTOs: excludes the primary key
	 * and the auto-managed timestamp columns.
	 *
	 * @return Field[]
	 */
	public function editable(): array
	{
		return array_values( array_filter(
			$this->fields,
			fn( Field $f ) => !$f->isPrimary && !$f->isTimestamp()
		) );
	}

	/**
	 * Fields suitable for an index listing (first few editable columns).
	 *
	 * @param int $max Maximum number of columns to display
	 * @return Field[]
	 */
	public function listable( int $max = 4 ): array
	{
		$listable = array_values( array_filter(
			$this->editable(),
			fn( Field $f ) => !$f->isTextarea()
		) );

		return array_slice( $listable, 0, $max );
	}
}
