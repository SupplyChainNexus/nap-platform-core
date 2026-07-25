<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Commands\IngestAudatexClaimHandler;
use NAP\Infrastructure\Http\Controllers\ClaimIngestController;
use NAP\Infrastructure\Security\HmacSignatureVerifier;
use NAP\Infrastructure\Security\JwtAuthenticator;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function testHmacSignatureVerification(): void
    {
        $secret = "super-secret-audatex-key-2026";
        $verifier = new HmacSignatureVerifier($secret);

        $payload = '{"caseId":"NXC-100","claimNumber":"CLM-001"}';
        $validSignature = hash_hmac("sha256", $payload, $secret);

        $this->assertTrue($verifier->verify($payload, $validSignature));
        $this->assertFalse($verifier->verify($payload, "invalid-sig"));
    }

    public function testClaimIngestControllerRejectsInvalidHmacSignature(): void
    {
        $secret = "webhook-secret-key-123";
        $verifier = new HmacSignatureVerifier($secret);
        $handler = new IngestAudatexClaimHandler();
        $controller = new ClaimIngestController($handler, $verifier);

        $payload = ["caseId" => "NXC-999", "document" => []];
        $rawBody = json_encode($payload) ?: "";

        // Unsigned request
        $response1 = $controller->handleWebhook($payload, $rawBody, null);
        /** @var array<string, mixed> $data1 */
        $data1 = json_decode($response1, true);
        $this->assertEquals("error", $data1["status"]);
        $this->assertEquals(401, $data1["code"]);

        // Signed request
        $validSignature = hash_hmac("sha256", $rawBody, $secret);
        $response2 = $controller->handleWebhook($payload, $rawBody, $validSignature);
        /** @var array<string, mixed> $data2 */
        $data2 = json_decode($response2, true);
        $this->assertEquals("success", $data2["status"]);
        $this->assertEquals(201, $data2["code"]);
    }

    public function testJwtAuthenticatorSuccessAndFailure(): void
    {
        $jwtSecret = "jwt-secret-key-32-chars-long!!";
        $authenticator = new JwtAuthenticator($jwtSecret, "nap-platform-auth");

        $token = $authenticator->generateToken("usr-999", ["role" => "EXECUTIVE"]);
        $claims = $authenticator->authenticate($token);

        $this->assertNotNull($claims);
        $this->assertEquals("usr-999", $claims["sub"]);
        $this->assertEquals("EXECUTIVE", $claims["role"]);

        // Manipulated token check
        $invalidToken = $token . "tampered";
        $this->assertNull($authenticator->authenticate($invalidToken));
    }
}