<?php

namespace Tests\Unit;

use App\Http\Controllers\AI\AiAssistanceTicketController;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

/**
 * `storeScreenshot()`'s decode-and-save logic, against `Storage::fake('local')` — an
 * isolated, in-memory-backed disk Laravel creates for exactly this purpose. Nothing
 * here touches the real filesystem or the database; the controller itself is built
 * without its constructor since only this one private method is under test.
 */
class AiAssistanceTicketScreenshotTest extends TestCase
{
    private function storeScreenshot(?string $dataUri, int $instituteId = 101): ?string
    {
        $controller = (new ReflectionClass(AiAssistanceTicketController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(AiAssistanceTicketController::class, 'storeScreenshot');
        $method->setAccessible(true);

        return $method->invoke($controller, $dataUri, $instituteId);
    }

    public function test_a_valid_png_data_uri_is_saved_and_its_path_is_scoped_to_the_institute(): void
    {
        Storage::fake('local');

        // A minimal 1x1 transparent PNG, base64-encoded — small and real, not a stub string.
        $pixel = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $path = $this->storeScreenshot('data:image/png;base64,' . $pixel, 101);

        $this->assertNotNull($path);
        $this->assertStringStartsWith('ai-assistance-tickets/101/', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame(base64_decode($pixel), Storage::disk('local')->get($path));
    }

    public function test_null_is_returned_and_nothing_is_written_when_no_screenshot_is_given(): void
    {
        Storage::fake('local');

        $this->assertNull($this->storeScreenshot(null));
        $this->assertSame([], Storage::disk('local')->allFiles('ai-assistance-tickets'));
    }

    public function test_a_non_image_data_uri_is_refused_rather_than_saved(): void
    {
        Storage::fake('local');

        $this->assertNull($this->storeScreenshot('data:text/plain;base64,aGVsbG8='));
        $this->assertSame([], Storage::disk('local')->allFiles('ai-assistance-tickets'));
    }

    public function test_malformed_base64_does_not_throw_and_saves_nothing(): void
    {
        Storage::fake('local');

        $this->assertNull($this->storeScreenshot('data:image/png;base64,not-real-base64!!!'));
        $this->assertSame([], Storage::disk('local')->allFiles('ai-assistance-tickets'));
    }
}
