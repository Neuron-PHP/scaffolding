<?php

namespace Neuron\Scaffolding\Schema;

use Neuron\Data\Database\ConnectionFactory;
use PDO;
use Exception;

/**
 * PDO factory for schema introspection.
 *
 * Builds a connection from a `database` settings array
 * (adapter/host/port/name/user/pass, or a url) by delegating to the
 * framework-wide {@see ConnectionFactory} so scaffolding accepts exactly the
 * configuration the application itself uses.
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
		try
		{
			return ConnectionFactory::createFromConfig( $config );
		}
		catch( \InvalidArgumentException $e )
		{
			throw new Exception( $e->getMessage(), 0, $e );
		}
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
}
