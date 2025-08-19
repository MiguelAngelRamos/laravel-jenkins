# Guía práctica de PHPUnit para pruebas unitarias

Esta guía resume los conceptos y métodos más útiles de PHPUnit para escribir pruebas unitarias profesionales, con ejemplos claros y notas de buenas prácticas. Incluye ciclo de vida, aserciones, mocks/doubles, data providers, manejo de excepciones, dependencias, skips/requirements y organización.

## 1. Estructura básica de una prueba

```php
use PHPUnit\Framework\TestCase;

class MiClaseTest extends TestCase
{
    public function test_suma_basica()
    {
        // Arrange
        $a = 2; $b = 3;
        // Act
        $resultado = $a + $b;
        // Assert
        $this->assertSame(5, $resultado);
    }
}
```

- Nombres recomendados: `it_describe_el_comportamiento()` o `test_lo_que_esperas()`.
- Patrón AAA: Arrange (prepara), Act (ejecuta), Assert (verifica).

## 2. Ciclo de vida de PHPUnit

- `setUp(): void` — se ejecuta antes de cada prueba.
- `tearDown(): void` — se ejecuta después de cada prueba.
- `setUpBeforeClass(): void` — estático; antes de todas las pruebas de la clase.
- `tearDownAfterClass(): void` — estático; después de todas las pruebas de la clase.
- Anotaciones alternativas:
  - `@before` y `@after` (métodos marcados así se ejecutan antes/después de cada test).

Ejemplo:
```php
protected function setUp(): void
{
    parent::setUp();
    // Inicialización común
}

protected function tearDown(): void
{
    // Limpieza
    parent::tearDown();
}
```

## 3. Marcar métodos de prueba

- Prefijo `test_` en el nombre del método, o
- Anotación `@test` sobre el método, o
- En PHPUnit 10+, también se admiten atributos `#[Test]`.

## 4. Aserciones más usadas

- Igualdad y tipos:
  - `assertSame($esperado, $actual)` — igualdad estricta (===).
  - `assertEquals($esperado, $actual)` — igualdad flexible (==).
  - `assertNotSame`, `assertNotEquals`.
  - `assertNull`, `assertNotNull`, `assertTrue`, `assertFalse`.
  - `assertInstanceOf(Clase::class, $obj)`.
- Strings:
  - `assertStringContainsString('sub', $cadena)`.
  - `assertMatchesRegularExpression('/^foo/', $cadena)`.
- Arrays y colecciones:
  - `assertCount(3, $array)`.
  - `assertContains($valor, $array)`.
  - `assertArrayHasKey('clave', $array)`.
- Números:
  - `assertGreaterThan($min, $actual)` / `assertLessThan($max, $actual)`.
  - `assertEqualsWithDelta($esperado, $actual, $delta)` para flotantes.
- Excepciones:
  - `$this->expectException(\RuntimeException::class);`
  - `$this->expectExceptionMessage('mensaje');`
  - `$this->expectExceptionCode(123);`
- JSON:
  - `assertJsonStringEqualsJsonString($jsonEsperado, $jsonActual)`.
- Aserción genérica:
  - `assertThat($actual, $constraint)` con constraints avanzados.

## 5. Dobles de prueba (mocks, stubs) con PHPUnit

Creación rápida de mocks:
```php
$repo = $this->createMock(Repositorio::class);
$repo->expects($this->once())          // veces esperadas: once, never, atLeastOnce, exactly(n)
     ->method('guardar')               // método a interceptar
     ->with($this->equalTo($payload))  // validación de argumentos: equalTo, identicalTo, anything, callback
     ->willReturn($valorSimulado);     // comportamiento: willReturn, willReturnMap, willReturnCallback, willThrowException
```

- `equalTo($x)`: compara por valor/contenido.
- `identicalTo($x)`: exige la misma instancia (===).
- `anything()`: cualquier valor.
- `callback(fn($a) => condición)`: validación personalizada.
- Métodos void: no uses `willReturn`; solo configura `expects(...)->method('...')`.
- Simular errores: `->willThrowException(new \RuntimeException('falló'))`.

Nota pedagógica: Configuras la simulación en Arrange; se ejecuta cuando tu SUT invoca el método del mock en el Act; PHPUnit verifica las expectativas al final del test.

## 6. Data Providers

Permiten ejecutar el mismo test con múltiples datasets.
```php
/** @dataProvider proveeSumas */
public function test_sumas($a, $b, $esperado)
{
    $this->assertSame($esperado, $a + $b);
}

public function proveeSumas(): array
{
    return [
        'caso_pequeño' => [1, 2, 3],
        'cero'         => [0, 0, 0],
        'negativos'    => [-2, -3, -5],
    ];
}
```

## 7. Dependencias entre pruebas

```php
public function test_crea_usuario(): Usuario
{
    $u = new Usuario('ana');
    $this->assertSame('ana', $u->nombre());
    return $u; // valor para la dependiente
}

/** @depends test_crea_usuario */
public function test_asigna_rol(Usuario $u)
{
    $u->asignarRol('admin');
    $this->assertTrue($u->tieneRol('admin'));
}
```

- Úsalas con moderación; preferible independencia entre tests.

## 8. Skips, incompletos y requisitos

- Saltar condicionalmente:
  - `$this->markTestSkipped('Motivo');`
- Marcar como incompleto:
  - `$this->markTestIncomplete('Pendiente de implementación');`
- Requisitos de entorno:
  - `@requires PHP >= 8.1`
  - `@requires extension json`

## 9. Grupos y tamaños

- `@group lento` para agrupar pruebas.
- Tamaños: `@small`, `@medium`, `@large` (controlan timeouts por convención de PHPUnit).

## 10. Organización y nombres

- Carpetas (en Laravel):
  - Unit: `tests/Unit` (sin BD ni framework; usa `PHPUnit\Framework\TestCase`).
  - Feature: `tests/Feature` (integración; en Laravel extiende `Tests\TestCase`).
- Nombre de archivo: `<Clase>Test.php`.
- Nombrado de métodos: describe comportamiento esperado (`it_hace_algo_interesante`).

## 11. Manejo de tiempo y fechas

- Evita usar `time()` directamente; abstrae el reloj o usa inyección para testear.
- Para comparar tiempos, usa tolerancias (`assertEqualsWithDelta`).

## 12. Ejemplos de patrones comunes

- Verificar que NO se llame al repositorio en cierta condición:
```php
$repo = $this->createMock(Repo::class);
$repo->expects($this->never())->method('guardar');
$servicio = new Servicio($repo);
$servicio->operarConEntradaInvalida($input);
```

- Verificar callback en argumentos:
```php
$repo->expects($this->once())
     ->method('guardar')
     ->with($this->callback(fn($dto) => $dto->valido() && $dto->id() === 10));
```

## 13. Errores y excepciones

```php
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('ID inválido');
$servicio->consultar(-1);
```

- Coloca `expectException*` antes de la línea que dispara la excepción.

## 14. Ejecución de pruebas

- Todo el proyecto (Laravel):
  ```bash
  php artisan test
  ```
- Directo con PHPUnit:
  ```bash
  vendor/bin/phpunit
  ```
- Filtrar por clase/método/grupo:
  ```bash
  php artisan test --filter=MiClaseTest
  php artisan test --filter=it_hace_algo
  php artisan test --group=lento
  ```

## 15. Buenas prácticas (checklist)

- [ ] Un test = un motivo claro de fallo.
- [ ] AAA visible y limpio.
- [ ] Nombres descriptivos y consistentes.
- [ ] Sin dependencias externas en unitarias (usa mocks/stubs).
- [ ] Cubre happy path + 1-2 casos borde/errores.
- [ ] Evita fixtures pesadas; reutiliza helpers si suman claridad.
- [ ] Mantén las aserciones precisas y con buenos mensajes.

## 16. Notas para Laravel

- Pruebas unitarias puras: extienden `PHPUnit\Framework\TestCase` (sin framework).
- Pruebas de integración (Feature): extienden `Tests\TestCase` (acceso a helpers de Laravel, e.g., `RefreshDatabase`, `actingAs`, HTTP JSON, etc.).
- Para BD en Feature: usa `RefreshDatabase` y SQLite de pruebas.

---

Referencias:
- PHPUnit Manual: https://phpunit.de/documentation.html
- Assertions (lista completa): https://phpunit.readthedocs.io
- Test doubles: https://phpunit.readthedocs.io/en/latest/test-doubles.html

