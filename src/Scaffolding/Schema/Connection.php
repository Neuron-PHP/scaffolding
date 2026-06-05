<?php

namespace Neuron\Scaffolding\Schema;

use PDO;
use Exception;

/**
 * Minimal, dependency-free PDO factory for schema introspection.
 *
 * The scaffolding package does not depend on neuron-php/cms, so it cannot use
 * the CMS ConnectionFactory. This helper builds a read-only PDO connection
 * from a `database` settings array (adapter/host/port/name/user/pass, or a url).
 *
 * @package Neuron\Scaffolding\Schema
 */
class Connection
{
	/**
	 * Create a PDO connection from a database configuration array.
	 *
	 * @param array $config
	 * @return PDO
	 * @throws Exception if the adapter is unsupported or configuration is invalid
	 */
	public static function fromConfig( array $config ): PDO
	{
		if( !empty( $config[ 'url' ] ) )
		{
			$config = array_merge( self::parseUrl( $config[ 'url' ] ), array_filter(
				$config,
				fn( $value, $key ) => $key !== 'url' && $value !== null,
				ARRAY_FILTER_USE_BOTH
			) );
		}

		$adapter = $config[ 'adapter' ] ?? 'sqlite';

		if( empty( $config[ 'name' ] ) )
		{
			throw new Exception( 'Database "name" configuration is required for introspection.' );
		}

		$dsn = match( $adapter )
		{
			'sqlite' => "sqlite:{$config[ 'name' ]}",
			'mysql'  => sprintf(
				'mysql:host=%s;port=%s;dbname=%s;charset=%s',
				$config[ 'host' ] ?? 'localhost',
				$config[ 'port' ] ?? 3306,
				$config[ 'name' ],
				$config[ 'charset' ] ?? 'utf8mb4'
			),
			'pgsql'  => sprintf(
				'pgsql:host=%s;port=%s;dbname=%s',
				$config[ 'host' ] ?? 'localhost',
				$config[ 'port' ] ?? 5432,
				$config[ 'name' ]
			),
			default  => throw new Exception( "Unsupported database adapter: $adapter" )
		};

		return new PDO(
			$dsn,
			$config[ 'user' ] ?? $config[ 'username' ] ?? null,
			$config[ 'pass' ] ?? $config[ 'password' ] ?? null,
			[
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			]
		);
	}

	/**
	 * Detect the adapter name for a PDO connection.
	 *
	 * @param PDO $pdo
	 * @return string sqlite|mysql|pgsql
	 */
	public static function adapter( PDO $pdo ): string
	{
		return $pdo->getAttribute( PDO::ATTR_DRIVER_NAME );
	}

	/**
	 * Parse a database URL into a configuration array.
	 *
	 * @param string $url
	 * @return array
	 * @throws Exception if the URL is malformed
	 */
	private static function parseUrl( string $url ): array
	{
		if( str_starts_with( $url, 'sqlite:' ) )
		{
			$path = substr( $url, 7 );

			if( $path === ':memory:' )
			{
				return [ 'adapter' => 'sqlite', 'name' => ':memory:' ];
			}

			if( str_starts_with( $path, '///' ) )
			{
				$path = substr( $path, 2 );
			}
			elseif( str_starts_with( $path, '//' ) )
			{
				$path = substr( $path, 2 );
			}

			return [ 'adapter' => 'sqlite', 'name' => $path ];
		}

		$parsed = parse_url( $url );

		if( $parsed === false || !isset( $parsed[ 'scheme' ] ) )
		{
			throw new Exception( "Malformed database URL: $url" );
		}

		$adapter = match( $parsed[ 'scheme' ] )
		{
			'mysql'                            => 'mysql',
			'postgresql', 'postgres', 'pgsql'  => 'pgsql',
			default => throw new Exception( "Unsupported database scheme: {$parsed[ 'scheme' ]}" )
		};

		$config = [ 'adapter' => $adapter ];

		if( isset( $parsed[ 'host' ] ) ) { $config[ 'host' ] = $parsed[ 'host' ]; }
		if( isset( $parsed[ 'port' ] ) ) { $config[ 'port' ] = $parsed[ 'port' ]; }
		if( isset( $parsed[ 'user' ] ) ) { $config[ 'user' ] = rawurldecode( $parsed[ 'user' ] ); }
		if( isset( $parsed[ 'pass' ] ) ) { $config[ 'pass' ] = rawurldecode( $parsed[ 'pass' ] ); }

		if( isset( $parsed[ 'path' ] ) && $parsed[ 'path' ] !== '/' )
		{
			$config[ 'name' ] = ltrim( $parsed[ 'path' ], '/' );
		}

		return $config;
	}
}
