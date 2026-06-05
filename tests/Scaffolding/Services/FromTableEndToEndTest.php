<?php

namespace Tests\Scaffolding\Services;

use PHPUnit\Framework\TestCase;
use Neuron\Core\System\MemoryFileSystem;
use Neuron\Core\System\RealFileSystem;
use Neuron\Scaffolding\Schema\SchemaIntrospector;
use Neuron\Scaffolding\Services\FileTemplateEngine;
use Neuron\Scaffolding\Services\ResourceScaffolder;
use PDO;

/**
 * End-to-end coverage of the --from-table path: introspect a real (temp) SQLite
 * table, then generate the entire CRUD stack and assert the output is both
 * field-aware and syntactically valid PHP.
 */
class FromTableEndToEndTest extends TestCase
{
	private string $dbPath;

	protected function setUp(): void
	{
		$this->dbPath = sys_get_temp_dir() . '/scaffold_e2e_' . uniqid() . '.db';

		$pdo = new PDO( 'sqlite:' . $this->dbPath );
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$pdo->exec(
			'CREATE TABLE jud_docket (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				case_number VARCHAR(50) NOT NULL,
				description TEXT,
				amount DECIMAL(10,2),
				is_closed BOOLEAN DEFAULT 0,
				hearing_date DATETIME
			)'
		);
	}

	protected function tearDown(): void
	{
		if( file_exists( $this->dbPath ) )
		{
			unlink( $this->dbPath );
		}
	}

	private function info(): array
	{
		return [
			'class'            => 'DocketController',
			'subNamespace'     => '',
			'model'            => 'Docket',
			'models'           => 'Dockets',
			'variable'         => 'docket',
			'variables'        => 'dockets',
			'tableName'        => 'jud_docket',
			'controllerPath'   => 'Dockets',
			'routePrefix'      => '/dockets',
			'namespace'        => 'App\\Controllers',
			'modelNamespace'   => 'App\\Models',
			'repoNamespace'    => 'App\\Repositories',
			'repoInterface'    => 'IDocketRepository',
			'repoClass'        => 'DatabaseDocketRepository',
			'viewPath'         => 'dockets',
			'routeName'        => 'dockets',
			'isApi'            => false,
			'primaryKey'       => 'id',
			'primaryKeyGetter' => 'getId',
		];
	}

	public function testGeneratesRunnableStackFromIntrospectedTable(): void
	{
		$pdo    = new PDO( 'sqlite:' . $this->dbPath );
		$fields = new SchemaIntrospector( $pdo )->introspect( 'jud_docket' );

		$fs = new MemoryFileSystem();
		$fs->setCwd( '/app' );

		$stubPath  = __DIR__ . '/../../../src/Scaffolding/Stubs';
		$templates = new FileTemplateEngine( new RealFileSystem(), $stubPath );
		$scaffolder = new ResourceScaffolder( $fs, $templates, '/app' );

		$info = $this->info();
		$scaffolder->generateModel( $info, $fields );
		$scaffolder->generateDto( $info, $fields );
		$scaffolder->generateRepository( $info );
		$scaffolder->generateController( $info );
		$scaffolder->generateViews( $info, $fields );

		$files = $fs->getFiles();

		$expected = [
			'/app/app/Models/Docket.php',
			'/app/resources/dtos/jud_docket.yaml',
			'/app/app/Repositories/IDocketRepository.php',
			'/app/app/Repositories/DatabaseDocketRepository.php',
			'/app/app/Controllers/DocketController.php',
			'/app/resources/views/dockets/index.php',
			'/app/resources/views/dockets/create.php',
			'/app/resources/views/dockets/edit.php',
			'/app/resources/views/dockets/show.php',
			'/app/resources/views/dockets/_form.php',
		];

		foreach( $expected as $path )
		{
			$this->assertArrayHasKey( $path, $files, "Missing generated file: $path" );
			$this->assertStringNotContainsString( '{{', $files[ $path ], "Unreplaced placeholder in: $path" );
		}

		$model = $files['/app/app/Models/Docket.php'];
		$this->assertStringContainsString( "#[Table('jud_docket', primaryKey: 'id')]", $model );
		$this->assertStringContainsString( '$_caseNumber', $model );
		$this->assertStringContainsString( 'public function getCaseNumber()', $model );

		$dto = $files['/app/resources/dtos/jud_docket.yaml'];
		$this->assertStringContainsString( 'case_number:', $dto );
		$this->assertStringContainsString( 'required: true', $dto );

		$form = $files['/app/resources/views/dockets/_form.php'];
		$this->assertStringContainsString( 'name="case_number"', $form );
		$this->assertStringContainsString( 'csrf_field()', $form );

		// Every generated PHP file must be syntactically valid.
		foreach( $files as $path => $content )
		{
			if( !str_ends_with( $path, '.php' ) )
			{
				continue;
			}

			$tmp = tempnam( sys_get_temp_dir(), 'lint' ) . '.php';
			file_put_contents( $tmp, $content );
			$output = [];
			$status = 0;
			exec( 'php -l ' . escapeshellarg( $tmp ) . ' 2>&1', $output, $status );
			unlink( $tmp );

			$this->assertSame( 0, $status, "Syntax error in $path:\n" . implode( "\n", $output ) );
		}
	}
}
