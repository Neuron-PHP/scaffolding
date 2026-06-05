<?php

namespace Neuron\Scaffolding\Schema;

/**
 * Normalized representation of a single database column / model field.
 *
 * A Field carries the logical type plus derivation helpers used by the
 * model, DTO, repository, view and migration generators so that every
 * generator agrees on naming, PHP types, validator types and HTML inputs.
 *
 * @package Neuron\Scaffolding\Schema
 */
class Field
{
	/**
	 * @param string $name Column name (snake_case)
	 * @param string $type Logical type (string, text, integer, biginteger, float, decimal, boolean, date, datetime, time, json, email, uuid)
	 * @param bool $nullable Whether the column accepts null
	 * @param int|null $length Maximum length for string types
	 * @param mixed $default Column default value
	 * @param bool $isPrimary Whether this is the primary key
	 * @param bool $autoIncrement Whether the primary key auto-increments
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $type = 'string',
		public readonly bool $nullable = false,
		public readonly ?int $length = null,
		public readonly mixed $default = null,
		public readonly bool $isPrimary = false,
		public readonly bool $autoIncrement = false
	) {}

	/**
	 * The camelCase form of the column name (e.g. author_id -> authorId).
	 */
	public function camelName(): string
	{
		return lcfirst( str_replace( '_', '', ucwords( $this->name, '_' ) ) );
	}

	/**
	 * The StudlyCase form of the column name (e.g. author_id -> AuthorId).
	 */
	public function studlyName(): string
	{
		return ucfirst( $this->camelName() );
	}

	/**
	 * The model property name (underscore-prefixed camelCase).
	 */
	public function propertyName(): string
	{
		return '_' . $this->camelName();
	}

	/**
	 * The getter method name.
	 */
	public function getter(): string
	{
		return 'get' . $this->studlyName();
	}

	/**
	 * The setter method name.
	 */
	public function setter(): string
	{
		return 'set' . $this->studlyName();
	}

	/**
	 * Human friendly label (e.g. author_id -> Author Id).
	 */
	public function label(): string
	{
		return ucwords( str_replace( '_', ' ', $this->name ) );
	}

	/**
	 * Whether this field represents a date/time value.
	 */
	public function isDateTime(): bool
	{
		return in_array( $this->type, [ 'date', 'datetime', 'time' ], true );
	}

	/**
	 * Whether this field is one of the auto-managed timestamp columns.
	 */
	public function isTimestamp(): bool
	{
		return in_array( $this->name, [ 'created_at', 'updated_at' ], true );
	}

	/**
	 * The scalar PHP type without nullability prefix.
	 */
	public function phpBaseType(): string
	{
		return match( $this->type )
		{
			'integer', 'biginteger' => 'int',
			'float', 'decimal'      => 'float',
			'boolean'               => 'bool',
			'date', 'datetime', 'time' => '\DateTimeImmutable',
			'json'                  => 'array',
			default                 => 'string'
		};
	}

	/**
	 * Whether the model property should be declared nullable.
	 *
	 * Date/time properties are always nullable (no scalar default exists) as is
	 * the auto-increment primary key.
	 */
	public function isPhpNullable(): bool
	{
		return $this->nullable
			|| $this->isDateTime()
			|| ( $this->isPrimary && $this->autoIncrement );
	}

	/**
	 * The PHP type hint for the model property/getter.
	 */
	public function phpType(): string
	{
		return $this->isPhpNullable() ? '?' . $this->phpBaseType() : $this->phpBaseType();
	}

	/**
	 * The default value literal for a model property declaration.
	 */
	public function phpDefaultLiteral(): string
	{
		if( $this->isPhpNullable() )
		{
			return 'null';
		}

		return match( $this->phpBaseType() )
		{
			'int'   => '0',
			'float' => '0.0',
			'bool'  => 'false',
			'array' => '[]',
			default => "''"
		};
	}

	/**
	 * Expression that hydrates this field from a data array element.
	 *
	 * @param string $element PHP expression for the source value (e.g. "$data['name']")
	 * @return string
	 */
	public function fromArrayExpr( string $element ): string
	{
		return match( $this->phpBaseType() )
		{
			'int'              => "(int)$element",
			'float'            => "(float)$element",
			'bool'             => "(bool)$element",
			'array'            => "is_array( $element ) ? $element : (array)json_decode( (string)$element, true )",
			'\DateTimeImmutable' => "$element instanceof \\DateTimeImmutable ? $element : new \\DateTimeImmutable( (string)$element )",
			default            => "(string)$element"
		};
	}

	/**
	 * Expression that serializes this field for toArray().
	 */
	public function toArrayExpr(): string
	{
		$property = '$this->' . $this->propertyName();

		return match( $this->phpBaseType() )
		{
			'\DateTimeImmutable' => $property . "?->format( 'Y-m-d H:i:s' )",
			'array'              => 'json_encode( ' . $property . ' )',
			default              => $property
		};
	}

	/**
	 * The Neuron DTO validator type.
	 */
	public function dtoType(): string
	{
		return match( $this->type )
		{
			'integer', 'biginteger' => 'integer',
			'float', 'decimal'      => 'float',
			'boolean'               => 'boolean',
			'date'                  => 'date',
			'datetime'              => 'date_time',
			'time'                  => 'time',
			'email'                 => 'email',
			default                 => 'string'
		};
	}

	/**
	 * The Phinx migration column type.
	 */
	public function phinxType(): string
	{
		return match( $this->type )
		{
			'email', 'uuid' => 'string',
			default         => $this->type
		};
	}

	/**
	 * The HTML form input type for create/edit forms.
	 */
	public function htmlInputType(): string
	{
		return match( $this->type )
		{
			'integer', 'biginteger', 'float', 'decimal' => 'number',
			'boolean'  => 'checkbox',
			'date'     => 'date',
			'datetime' => 'datetime-local',
			'time'     => 'time',
			'email'    => 'email',
			default    => 'text'
		};
	}

	/**
	 * Whether the field should render as a textarea instead of an input.
	 */
	public function isTextarea(): bool
	{
		return $this->type === 'text' || $this->type === 'json';
	}
}
