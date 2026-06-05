<?php

namespace Neuron\Scaffolding\Services;

use Neuron\Core\System\IFileSystem;
use Neuron\Scaffolding\Contracts\ITemplateEngine;
use Neuron\Scaffolding\Schema\Field;
use Neuron\Scaffolding\Schema\FieldSet;
use RuntimeException;

/**
 * Generates the field-aware CRUD stack (model, DTO, repository, controller,
 * views) from a resolved resource {@see $info} array and a {@see FieldSet}.
 *
 * The placeholder template engine only performs flat string substitution, so
 * all per-field code/markup is pre-rendered here and injected as placeholder
 * values.
 *
 * @package Neuron\Scaffolding\Services
 */
class ResourceScaffolder
{
	/**
	 * @param IFileSystem $fs
	 * @param ITemplateEngine $templates
	 * @param string $projectPath Absolute project root
	 */
	public function __construct(
		private IFileSystem $fs,
		private ITemplateEngine $templates,
		private string $projectPath
	) {}

	/**
	 * Generate the controller file.
	 *
	 * @return string Path to the created file
	 */
	public function generateController( array $info, bool $force = false ): string
	{
		$namespace = $this->controllerNamespace( $info );
		$dir = $this->namespaceToDir( $namespace );
		$file = $dir . '/' . $info[ 'class' ] . '.php';

		$this->guardExisting( $file, $force, 'Controller' );

		$stub = !empty( $info[ 'isApi' ] ) ? 'controller.api.stub' : 'controller.resource.stub';
		$content = $this->templates->render( $stub, array_merge( $info, [ 'namespace' => $namespace ] ) );

		$this->put( $dir, $file, $content );

		return $file;
	}

	/**
	 * Generate the Neuron ORM model.
	 *
	 * @return string Path to the created file
	 */
	public function generateModel( array $info, FieldSet $fields, bool $force = false ): string
	{
		$dir = $this->namespaceToDir( $info[ 'modelNamespace' ] );
		$file = $dir . '/' . $info[ 'model' ] . '.php';

		$this->guardExisting( $file, $force, 'Model' );

		$data = array_merge( $info, [
			'properties'     => $this->buildModelProperties( $fields ),
			'accessors'      => $this->buildModelAccessors( $fields ),
			'fromArray'      => $this->buildModelFromArray( $fields ),
			'toArray'        => $this->buildModelToArray( $fields ),
			'toArrayPrimary' => $this->buildModelToArrayPrimary( $fields ),
		] );

		$content = $this->templates->render( 'model.stub', $data );
		$this->put( $dir, $file, $content );

		return $file;
	}

	/**
	 * Generate the DTO YAML definition under resources/dtos.
	 *
	 * @return string Path to the created file
	 */
	public function generateDto( array $info, FieldSet $fields, bool $force = false ): string
	{
		$dir = $this->projectPath . '/resources/dtos';
		$file = $dir . '/' . $info[ 'tableName' ] . '.yaml';

		$this->guardExisting( $file, $force, 'DTO' );

		$content = $this->templates->render( 'dto.yaml.stub', array_merge( $info, [
			'properties' => $this->buildDtoBody( $fields ),
		] ) );

		$this->put( $dir, $file, $content );

		return $file;
	}

	/**
	 * Generate the repository interface and database implementation.
	 *
	 * @return string[] Paths to the created files
	 */
	public function generateRepository( array $info, bool $force = false ): array
	{
		$dir = $this->namespaceToDir( $info[ 'repoNamespace' ] );

		$interfaceFile = $dir . '/' . $info[ 'repoInterface' ] . '.php';
		$classFile = $dir . '/' . $info[ 'repoClass' ] . '.php';

		$this->guardExisting( $interfaceFile, $force, 'Repository interface' );
		$this->guardExisting( $classFile, $force, 'Repository' );

		$this->put( $dir, $interfaceFile, $this->templates->render( 'repository.interface.stub', $info ) );
		$this->put( $dir, $classFile, $this->templates->render( 'repository.database.stub', $info ) );

		return [ $interfaceFile, $classFile ];
	}

	/**
	 * Generate the field-aware HTML views (index, create, edit, show, _form).
	 *
	 * @return string[] Paths to the created files
	 */
	public function generateViews( array $info, FieldSet $fields, bool $force = false ): array
	{
		$dir = $this->projectPath . '/resources/views/' . $info[ 'viewPath' ];
		$created = [];

		$data = array_merge( $info, [
			'indexHeaders' => $this->buildIndexHeaders( $fields ),
			'indexCells'   => $this->buildIndexCells( $fields, $info ),
			'formFields'   => $this->buildFormFields( $fields ),
			'showRows'     => $this->buildShowRows( $fields, $info ),
		] );

		$map = [
			'index'  => 'view.index.stub',
			'create' => 'view.create.stub',
			'edit'   => 'view.edit.stub',
			'show'   => 'view.show.stub',
			'_form'  => 'view.form.stub',
		];

		foreach( $map as $page => $stub )
		{
			$file = $dir . '/' . $page . '.php';

			if( $this->fs->fileExists( $file ) && !$force )
			{
				continue;
			}

			$this->put( $dir, $file, $this->templates->render( $stub, $data ) );
			$created[] = $file;
		}

		return $created;
	}

	/**
	 * The fully qualified controller namespace (base + sub-namespace).
	 */
	private function controllerNamespace( array $info ): string
	{
		$namespace = $info[ 'namespace' ];

		if( !empty( $info[ 'subNamespace' ] ) )
		{
			$namespace .= '\\' . $info[ 'subNamespace' ];
		}

		return $namespace;
	}

	/**
	 * Map a PHP namespace to a project-relative directory, treating the
	 * leading "App\" segment as the conventional "app/" directory.
	 */
	private function namespaceToDir( string $namespace ): string
	{
		$relative = str_replace( '\\', '/', $namespace );
		$relative = preg_replace( '#^App/#', 'app/', $relative );

		return $this->projectPath . '/' . $relative;
	}

	/**
	 * Ensure a directory exists then write a file, throwing on failure.
	 */
	private function put( string $dir, string $file, string $content ): void
	{
		if( !$this->fs->isDir( $dir ) && !$this->fs->mkdir( $dir, 0755, true ) )
		{
			throw new RuntimeException( "Could not create directory: $dir" );
		}

		if( $this->fs->writeFile( $file, $content ) === false )
		{
			throw new RuntimeException( "Could not write file: $file" );
		}
	}

	/**
	 * Throw if the target exists and overwrite was not requested.
	 */
	private function guardExisting( string $file, bool $force, string $label ): void
	{
		if( $this->fs->fileExists( $file ) && !$force )
		{
			throw new RuntimeException( "$label already exists: $file (use --force to overwrite)" );
		}
	}

	/**
	 * Build typed, defaulted property declarations for the model.
	 */
	private function buildModelProperties( FieldSet $fields ): string
	{
		$lines = [];

		foreach( $fields->all() as $field )
		{
			$lines[] = sprintf(
				"\tprivate %s $%s = %s;",
				$field->phpType(),
				$field->propertyName(),
				$field->phpDefaultLiteral()
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build fluent getters/setters for the model.
	 */
	private function buildModelAccessors( FieldSet $fields ): string
	{
		$blocks = [];

		foreach( $fields->all() as $field )
		{
			$type = $field->phpType();
			$prop = $field->propertyName();
			$camel = $field->camelName();

			$blocks[] = <<<PHP
	public function {$field->getter()}(): {$type}
	{
		return \$this->{$prop};
	}

	public function {$field->setter()}( {$type} \${$camel} ): self
	{
		\$this->{$prop} = \${$camel};
		return \$this;
	}
PHP;
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Build the body of fromArray().
	 */
	private function buildModelFromArray( FieldSet $fields ): string
	{
		$lines = [];

		foreach( $fields->all() as $field )
		{
			$element = "\$data['{$field->name}']";
			$expr = $field->fromArrayExpr( $element );

			$lines[] = <<<PHP
		if( array_key_exists( '{$field->name}', \$data ) && {$element} !== null )
		{
			\$model->{$field->setter()}( {$expr} );
		}
PHP;
		}

		return implode( "\n\n", $lines );
	}

	/**
	 * Build the non-primary entries of toArray().
	 */
	private function buildModelToArray( FieldSet $fields ): string
	{
		$lines = [];

		foreach( $fields->all() as $field )
		{
			if( $field->isPrimary )
			{
				continue;
			}

			$lines[] = sprintf( "\t\t\t'%s' => %s,", $field->name, $field->toArrayExpr() );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build the conditional inclusion of the primary key in toArray().
	 */
	private function buildModelToArrayPrimary( FieldSet $fields ): string
	{
		$primary = $fields->primary();

		if( !$primary )
		{
			return '';
		}

		$prop = '$this->' . $primary->propertyName();

		return <<<PHP
		if( {$prop} !== null )
		{
			\$data['{$primary->name}'] = {$prop};
		}

PHP;
	}

	/**
	 * Build the DTO YAML property body.
	 */
	private function buildDtoBody( FieldSet $fields ): string
	{
		$lines = [];

		foreach( $fields->editable() as $field )
		{
			$lines[] = "  {$field->name}:";
			$lines[] = "    type: {$field->dtoType()}";
			$lines[] = '    required: ' . ( $field->nullable ? 'false' : 'true' );

			if( $field->dtoType() === 'string' && $field->length )
			{
				$lines[] = '    length:';
				$lines[] = "      max: {$field->length}";
			}

			$lines[] = '';
		}

		return rtrim( implode( "\n", $lines ), "\n" );
	}

	/**
	 * Build the <th> cells for the index table.
	 */
	private function buildIndexHeaders( FieldSet $fields ): string
	{
		$lines = [];

		foreach( $fields->listable() as $field )
		{
			$lines[] = sprintf( "\t\t\t<th>%s</th>", htmlspecialchars( $field->label() ) );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build the <td> cells for the index table, referencing the loop variable.
	 */
	private function buildIndexCells( FieldSet $fields, array $info ): string
	{
		$var = '$' . $info[ 'variable' ];
		$lines = [];

		foreach( $fields->listable() as $field )
		{
			$accessor = $var . '->' . $field->getter() . '()';
			$value = $field->phpBaseType() === '\DateTimeImmutable'
				? "{$accessor}?->format( 'Y-m-d H:i:s' )"
				: $accessor;

			$lines[] = sprintf(
				"\t\t\t<td><?= htmlspecialchars( (string)( %s ) ) ?></td>",
				$value
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build the form field markup for create/edit.
	 */
	private function buildFormFields( FieldSet $fields ): string
	{
		$blocks = [];

		foreach( $fields->editable() as $field )
		{
			$name = $field->name;
			$label = htmlspecialchars( $field->label() );
			$value = "\$values['{$name}'] ?? ''";
			$error = "\$errors['{$name}']";

			if( $field->isTextarea() )
			{
				$control = "\t\t<textarea id=\"{$name}\" name=\"{$name}\"><?= htmlspecialchars( (string)( {$value} ) ) ?></textarea>";
			}
			elseif( $field->htmlInputType() === 'checkbox' )
			{
				$control = "\t\t<input type=\"hidden\" name=\"{$name}\" value=\"0\">\n"
					. "\t\t<input type=\"checkbox\" id=\"{$name}\" name=\"{$name}\" value=\"1\"<?= ( {$value} ) ? ' checked' : '' ?>>";
			}
			else
			{
				$type = $field->htmlInputType();
				$control = "\t\t<input type=\"{$type}\" id=\"{$name}\" name=\"{$name}\" value=\"<?= htmlspecialchars( (string)( {$value} ) ) ?>\">";
			}

			$blocks[] = <<<HTML
	<div class="form-group">
		<label for="{$name}">{$label}</label>
{$control}
<?php if( isset( {$error} ) ): ?>
		<span class="error"><?= htmlspecialchars( is_array( {$error} ) ? implode( ', ', {$error} ) : (string){$error} ) ?></span>
<?php endif; ?>
	</div>
HTML;
		}

		return implode( "\n", $blocks );
	}

	/**
	 * Build the definition rows for the show view.
	 */
	private function buildShowRows( FieldSet $fields, array $info ): string
	{
		$var = '$' . $info[ 'variable' ];
		$lines = [];

		foreach( $fields->all() as $field )
		{
			$accessor = $var . '->' . $field->getter() . '()';
			$value = $field->phpBaseType() === '\DateTimeImmutable'
				? "{$accessor}?->format( 'Y-m-d H:i:s' )"
				: $accessor;

			$lines[] = sprintf( "\t<dt>%s</dt>", htmlspecialchars( $field->label() ) );
			$lines[] = sprintf( "\t<dd><?= htmlspecialchars( (string)( %s ) ) ?></dd>", $value );
		}

		return implode( "\n", $lines );
	}
}
