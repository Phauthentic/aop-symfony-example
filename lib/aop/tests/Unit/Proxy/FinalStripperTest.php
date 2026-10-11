<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Proxy\FinalStripper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FinalStripper::class)]
final class FinalStripperTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sources(): iterable
    {
        yield 'final class' => ["<?php\nfinal class A {}\n", "<?php\nclass A {}\n"];
        yield 'final method' => [
            "<?php\nclass A\n{\n    final public function a(): void {}\n    public final function b(): void {}\n}\n",
            "<?php\nclass A\n{\n    public function a(): void {}\n    public function b(): void {}\n}\n",
        ];
        yield 'final constant' => ["<?php\nclass A { final public const X = 1; }\n", "<?php\nclass A { public const X = 1; }\n"];
        yield 'final readonly class' => ["<?php\nfinal readonly class A {}\n", "<?php\nreadonly class A {}\n"];
        yield 'readonly final class' => ["<?php\nreadonly final class A {}\n", "<?php\nreadonly class A {}\n"];
        yield 'attribute and docblock before final' => [
            "<?php\n/**\n * A final class.\n */\n#[\\Attr('final')]\nfinal class A {}\n",
            "<?php\n/**\n * A final class.\n */\n#[\\Attr('final')]\nclass A {}\n",
        ];
        yield 'final in strings, comments, heredoc and names untouched' => [
            "<?php\n// final\n# final\n/* final */\nfinal class A\n{\n    const FINAL = 'final';\n    public function final(): string { return \"final {\$this->final}\" . <<<TXT\n    final\n    TXT; }\n}\n",
            "<?php\n// final\n# final\n/* final */\nclass A\n{\n    const FINAL = 'final';\n    public function final(): string { return \"final {\$this->final}\" . <<<TXT\n    final\n    TXT; }\n}\n",
        ];
        yield 'already non-final' => ["<?php\nabstract class A { abstract public function a(): void; }\n", "<?php\nabstract class A { abstract public function a(): void; }\n"];
    }

    #[Test]
    #[DataProvider('sources')]
    public function stripsFinalModifiersOnly(string $source, string $expected): void
    {
        self::assertSame($expected, FinalStripper::strip($source));
        self::assertSame($expected, FinalStripper::strip($expected), 'idempotent');
    }

    #[Test]
    public function magicPathConstantsBecomeThoseOfTheOriginalFileWhenItIsGiven(): void
    {
        $source = "<?php\nfinal class A { const D = __DIR__; const F = __FILE__; const S = '__DIR__ __FILE__'; }\n";

        self::assertSame(
            "<?php\nclass A { const D = '/app/src'; const F = '/app/src/A\\'s.php'; const S = '__DIR__ __FILE__'; }\n",
            FinalStripper::strip($source, "/app/src/A's.php"),
        );
        self::assertSame("<?php\nclass A { const D = __DIR__; const F = __FILE__; const S = '__DIR__ __FILE__'; }\n", FinalStripper::strip($source));
    }
}
