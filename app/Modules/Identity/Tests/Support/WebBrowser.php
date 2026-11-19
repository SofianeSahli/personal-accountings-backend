<?php

declare(strict_types=1);

namespace App\Modules\Identity\Tests\Support;

use App\Modules\Identity\database\factories\UserFactory;
use App\Modules\Identity\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;

final class WebBrowser
{
    public const ORIGIN_HOST = 'app.expenses.test';

    private const CSRF_COOKIE = 'XSRF-TOKEN';

    private const CSRF_COOKIE_PATH = '/sanctum/csrf-cookie';

    /**
     * @var array<string, string>
     */
    private array $cookies = [];

    private function __construct(private readonly bool $sendsCsrfToken) {}

    public static function open(bool $sendsCsrfToken = true): self
    {
        config([
            'session.driver' => 'database',
            'sanctum.stateful' => [self::ORIGIN_HOST],
            'sanctum.middleware.validate_csrf_token' => EnforcedCsrf::class,
        ]);

        $browser = new self($sendsCsrfToken);
        $browser->send('GET', self::CSRF_COOKIE_PATH)->assertNoContent();

        return $browser;
    }

    public static function signedInAs(User $user): self
    {
        $browser = self::open();
        $browser->postJson('/api/v1/auth/email/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.status', 'signed_in');

        return $browser;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    public function postJson(string $uri, array $body = []): TestResponse
    {
        return $this->send('POST', $uri, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    public function patchJson(string $uri, array $body = []): TestResponse
    {
        return $this->send('PATCH', $uri, $body);
    }

    /**
     * @return TestResponse<Response>
     */
    public function getJson(string $uri): TestResponse
    {
        return $this->send('GET', $uri);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    public function deleteJson(string $uri, array $body = []): TestResponse
    {
        return $this->send('DELETE', $uri, $body);
    }

    public function sessionId(): string
    {
        return $this->decrypted((string) config('session.cookie'));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function send(string $method, string $uri, array $body = []): TestResponse
    {
        Auth::forgetGuards();

        $response = call($method, $uri, [], $this->cookies, [], $this->headers(), (string) json_encode($body));
        $this->keepCookiesOf($response);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'HTTP_ORIGIN' => 'https://'.self::ORIGIN_HOST,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        if ($this->sendsCsrfToken && isset($this->cookies[self::CSRF_COOKIE])) {
            $headers['HTTP_X_XSRF_TOKEN'] = $this->cookies[self::CSRF_COOKIE];
        }

        return $headers;
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function keepCookiesOf(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->isCleared()) {
                unset($this->cookies[$cookie->getName()]);

                continue;
            }

            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }
    }

    private function decrypted(string $cookieName): string
    {
        return CookieValuePrefix::remove(Crypt::decryptString($this->cookies[$cookieName] ?? ''));
    }
}
