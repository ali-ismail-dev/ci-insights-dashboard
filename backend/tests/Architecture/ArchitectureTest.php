<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\TestCase;

/**
 * Architecture Test
 *
 * Tests application architecture and coding standards.
 *
 * @package Tests\Architecture
 */
class ArchitectureTest extends TestCase
{
    public function test_actions_follow_naming_convention(): void
    {
        $actionFiles = File::allFiles(app_path('Actions'));

        foreach ($actionFiles as $file) {
            $className = $this->getClassNameFromFile($file);

            // Actions should end with "Action"
            $this->assertStringEndsWith('Action', $className,
                "Action class {$className} should end with 'Action'");
        }
    }

    public function test_jobs_follow_naming_convention(): void
    {
        $jobFiles = File::allFiles(app_path('Jobs'));

        foreach ($jobFiles as $file) {
            $className = $this->getClassNameFromFile($file);

            // Jobs should end with "Job"
            $this->assertStringEndsWith('Job', $className,
                "Job class {$className} should end with 'Job'");
        }
    }

    public function test_controllers_follow_naming_convention(): void
    {
        $controllerFiles = File::allFiles(app_path('Http/Controllers'));

        foreach ($controllerFiles as $file) {
            $className = $this->getClassNameFromFile($file);

            // Controllers should end with "Controller"
            $this->assertStringEndsWith('Controller', $className,
                "Controller class {$className} should end with 'Controller'");
        }
    }

    public function test_models_follow_naming_convention(): void
    {
        $modelFiles = File::allFiles(app_path('Models'));

        foreach ($modelFiles as $file) {
            $className = $this->getClassNameFromFile($file);

            // Models should be in PascalCase and not contain underscores
            $this->assertDoesNotMatchRegularExpression('/_/', $className,
                "Model class {$className} should not contain underscores");

            // Should not end with common suffixes that indicate poor naming
            $this->assertDoesNotMatchRegularExpression('/(Model|Class|Object)$/i', $className,
                "Model class {$className} should not end with 'Model', 'Class', or 'Object'");
        }
    }

    public function test_request_classes_follow_naming_convention(): void
    {
        $requestFiles = File::allFiles(app_path('Http/Requests'));

        foreach ($requestFiles as $file) {
            $className = $this->getClassNameFromFile($file);

            // Request classes should end with "Request"
            $this->assertStringEndsWith('Request', $className,
                "Request class {$className} should end with 'Request'");
        }
    }

    public function test_service_providers_follow_naming_convention(): void
    {
        $providerFiles = File::allFiles(app_path('Providers'));

        foreach ($providerFiles as $file) {
            $className = $this->getClassNameFromFile($file);

            // Service providers should end with "ServiceProvider"
            $this->assertStringEndsWith('ServiceProvider', $className,
                "Service provider class {$className} should end with 'ServiceProvider'");
        }
    }

    public function test_actions_have_execute_method(): void
    {
        $actionFiles = File::allFiles(app_path('Actions'));

        foreach ($actionFiles as $file) {
            $content = File::get($file->getPathname());

            // Actions should have an execute method
            $this->assertStringContains('public function execute',
                $content,
                "Action class {$this->getClassNameFromFile($file)} should have an execute method");
        }
    }

    public function test_jobs_implement_should_queue(): void
    {
        $jobFiles = File::allFiles(app_path('Jobs'));

        foreach ($jobFiles as $file) {
            $content = File::get($file->getPathname());

            // Jobs should implement ShouldQueue
            $this->assertStringContains('implements ShouldQueue',
                $content,
                "Job class {$this->getClassNameFromFile($file)} should implement ShouldQueue");
        }
    }

    public function test_controllers_extend_base_controller(): void
    {
        $controllerFiles = File::allFiles(app_path('Http/Controllers'));
        $controllerFiles = array_filter($controllerFiles, function ($file) {
            return $file->getFilename() !== 'Controller.php'; // Exclude base Controller
        });

        foreach ($controllerFiles as $file) {
            $content = File::get($file->getPathname());

            // Controllers should extend the base Controller
            $this->assertStringContains('extends Controller',
                $content,
                "Controller class {$this->getClassNameFromFile($file)} should extend base Controller");
        }
    }

    public function test_models_use_correct_traits(): void
    {
        $modelFiles = File::allFiles(app_path('Models'));

        foreach ($modelFiles as $file) {
            $content = File::get($file->getPathname());

            // Models should use HasFactory trait
            $this->assertStringContains('use HasFactory',
                $content,
                "Model class {$this->getClassNameFromFile($file)} should use HasFactory trait");
        }
    }

    public function test_strict_types_declaration(): void
    {
        $phpFiles = File::allFiles(app_path());
        $phpFiles = array_filter($phpFiles, function ($file) {
            return $file->getExtension() === 'php';
        });

        foreach ($phpFiles as $file) {
            $content = File::get($file->getPathname());

            // All PHP files should have strict types declaration
            $this->assertStringStartsWith('<?php',
                $content,
                "File {$file->getPathname()} should start with '<?php'");

            $this->assertStringContains('declare(strict_types=1);',
                $content,
                "File {$file->getPathname()} should have strict types declaration");
        }
    }

    public function test_no_direct_database_queries_in_controllers(): void
    {
        $controllerFiles = File::allFiles(app_path('Http/Controllers'));

        foreach ($controllerFiles as $file) {
            $content = File::get($file->getPathname());

            // Controllers should not contain direct DB queries
            $this->assertDoesNotMatchRegularExpression('/DB::/',
                $content,
                "Controller {$this->getClassNameFromFile($file)} should not contain direct DB queries");

            $this->assertDoesNotMatchRegularExpression('/\\\\DB::/',
                $content,
                "Controller {$this->getClassNameFromFile($file)} should not contain direct DB queries");
        }
    }

    public function test_actions_are_single_responsibility(): void
    {
        $actionFiles = File::allFiles(app_path('Actions'));

        foreach ($actionFiles as $file) {
            $content = File::get($file->getPathname());
            $lines = explode("\n", $content);

            // Actions should be reasonably sized (less than 300 lines)
            $this->assertLessThan(300, count($lines),
                "Action class {$this->getClassNameFromFile($file)} is too large and may have multiple responsibilities");
        }
    }

    /**
     * Extract class name from file path
     */
    private function getClassNameFromFile(\SplFileInfo $file): string
    {
        $path = $file->getPathname();
        $relativePath = str_replace(app_path() . '/', '', $path);
        $relativePath = str_replace('.php', '', $relativePath);
        $relativePath = str_replace('/', '\\', $relativePath);

        return 'App\\' . $relativePath;
    }
}