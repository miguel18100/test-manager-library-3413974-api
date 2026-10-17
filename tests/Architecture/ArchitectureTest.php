<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * T-19 — Pruebas de arquitectura (artículos I, II y III de la constitución).
 *
 *  - El dominio no conoce nada de afuera (ni aplicación, ni adaptadores, ni el framework).
 *  - La aplicación depende del dominio y de sus propios puertos, nunca de adaptadores ni de Laravel.
 *  - Los adaptadores y HTTP pueden depender hacia adentro; ningún adaptador depende de HTTP.
 *  - Solo el proveedor de servicios (composición) une puertos con adaptadores.
 *
 * Las reglas se comprueban con un detector de importaciones; el último test demuestra que el
 * detector falla cuando alguien introduce una importación prohibida.
 */
final class ArchitectureTest extends TestCase
{
    /** carpeta => prefijos de espacio de nombres que NO puede importar */
    private const FORBIDDEN = [
        'Domain'      => ['Illuminate\\', 'App\\Application\\', 'App\\Adapters\\', 'App\\Http\\', 'Symfony\\'],
        'Application' => ['Illuminate\\', 'App\\Adapters\\', 'App\\Http\\', 'Symfony\\'],
        'Adapters'    => ['App\\Http\\'],
    ];

    /** @return list<string> importaciones `use ...;` y referencias `\Clase\Calificada` de un código PHP */
    public static function imports(string $code): array
    {
        preg_match_all('/^\s*use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+\w+)?\s*;/m', $code, $uses);

        return $uses[1];
    }

    /** @return list<string> descripciones de las violaciones encontradas en un fragmento */
    public static function violations(string $folder, string $code): array
    {
        $found = [];
        foreach (self::imports($code) as $import) {
            foreach (self::FORBIDDEN[$folder] ?? [] as $prefix) {
                if (str_starts_with($import, $prefix)) {
                    $found[] = "{$folder} importa {$import}";
                }
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function phpFiles(string $folder): array
    {
        $dir = dirname(__DIR__, 2).'/app/'.$folder;
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_layers_respect_the_dependency_rule(): void
    {
        $all = [];
        foreach (array_keys(self::FORBIDDEN) as $folder) {
            foreach ($this->phpFiles($folder) as $path) {
                foreach (self::violations($folder, file_get_contents($path)) as $violation) {
                    $all[] = basename($path).': '.$violation;
                }
            }
        }

        $this->assertSame([], $all, "Importaciones prohibidas:\n".implode("\n", $all));
    }

    public function test_only_the_service_provider_binds_ports_to_adapters(): void
    {
        $offenders = [];
        foreach (['Domain', 'Application', 'Http', 'Adapters', 'Console'] as $folder) {
            foreach ($this->phpFiles($folder) as $path) {
                if (preg_match('/->(?:singleton|bind)\(/', file_get_contents($path))) {
                    $offenders[] = basename($path);
                }
            }
        }

        $this->assertSame([], $offenders, 'La composición vive solo en AppServiceProvider (artículo III).');
    }

    public function test_every_port_is_an_interface_in_application(): void
    {
        foreach ($this->phpFiles('Application/Ports') as $path) {
            $this->assertMatchesRegularExpression('/^interface\s+\w+/m', file_get_contents($path), basename($path).' debe ser una interfaz.');
        }
    }

    public function test_the_detector_fails_when_a_forbidden_import_is_introduced(): void
    {
        $this->assertNotSame([], self::violations('Domain', "<?php\nuse Illuminate\\Support\\Facades\\DB;\n"));
        $this->assertNotSame([], self::violations('Application', "<?php\nuse App\\Adapters\\Persistence\\PostgresBookRepository;\n"));
        $this->assertNotSame([], self::violations('Adapters', "<?php\nuse App\\Http\\Controllers\\Controller;\n"));
        $this->assertSame([], self::violations('Domain', "<?php\nuse DateTimeImmutable;\n"));
    }
}
