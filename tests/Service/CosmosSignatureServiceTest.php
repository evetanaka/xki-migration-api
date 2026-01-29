<?php

namespace App\Tests\Service;

use App\Service\CosmosSignatureService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service de vérification de signatures Cosmos
 */
class CosmosSignatureServiceTest extends TestCase
{
    private CosmosSignatureService $service;

    protected function setUp(): void
    {
        $this->service = new CosmosSignatureService();
    }

    public function testGenerateClaimMessage(): void
    {
        $kiAddress = 'ki1qz5sd99vrwla22zr6xm4d0lzm63z5cs3rr3krh';
        $ethAddress = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $nonce = 'test-nonce-123';

        $message = $this->service->generateClaimMessage($kiAddress, $ethAddress, $nonce);

        $this->assertStringContainsString($kiAddress, $message);
        $this->assertStringContainsString($ethAddress, $message);
        $this->assertStringContainsString($nonce, $message);
        $this->assertStringContainsString('Timestamp:', $message);
        $this->assertStringContainsString('I authorize the migration of my XKI tokens', $message);
    }

    public function testGenerateClaimMessageFormat(): void
    {
        $kiAddress = 'ki1test';
        $ethAddress = '0xtest';
        $nonce = 'nonce123';

        $message = $this->service->generateClaimMessage($kiAddress, $ethAddress, $nonce);

        // Vérifie que le format du message est correct
        $this->assertMatchesRegularExpression(
            '/^I authorize the migration of my XKI tokens from .+ to .+\. Nonce: .+\. Timestamp: \d+$/',
            $message
        );
    }

    public function testVerifySignatureWithInvalidBase64Signature(): void
    {
        $message = 'test message';
        $invalidSignature = 'not-valid-base64!!!';
        $pubKey = base64_encode(str_repeat('x', 33));
        $address = 'ki1qz5sd99vrwla22zr6xm4d0lzm63z5cs3rr3krh';

        $result = $this->service->verifySignature($message, $invalidSignature, $pubKey, $address);

        $this->assertFalse($result);
    }

    public function testVerifySignatureWithInvalidBase64PubKey(): void
    {
        $message = 'test message';
        $signature = base64_encode(str_repeat('x', 64));
        $invalidPubKey = 'not-valid-base64!!!';
        $address = 'ki1qz5sd99vrwla22zr6xm4d0lzm63z5cs3rr3krh';

        $result = $this->service->verifySignature($message, $signature, $invalidPubKey, $address);

        $this->assertFalse($result);
    }

    public function testVerifySignatureWithWrongSignatureLength(): void
    {
        $message = 'test message';
        $shortSignature = base64_encode(str_repeat('x', 32)); // Devrait être 64 bytes
        $pubKey = base64_encode(str_repeat('x', 33));
        $address = 'ki1qz5sd99vrwla22zr6xm4d0lzm63z5cs3rr3krh';

        $result = $this->service->verifySignature($message, $shortSignature, $pubKey, $address);

        $this->assertFalse($result);
    }

    public function testVerifySignatureWithInvalidSignature(): void
    {
        $message = 'test message';
        // Génère une signature invalide (juste des bytes aléatoires)
        $invalidSignature = base64_encode(random_bytes(64));
        $pubKey = base64_encode(str_repeat('x', 33));
        $address = 'ki1qz5sd99vrwla22zr6xm4d0lzm63z5cs3rr3krh';

        $result = $this->service->verifySignature($message, $invalidSignature, $pubKey, $address);

        $this->assertFalse($result);
    }

    public function testVerifySignatureWithWrongAddress(): void
    {
        // Ce test vérifie qu'une signature valide mais avec une mauvaise adresse retourne false
        // Pour un test complet, il faudrait une vraie signature Cosmos
        // Ici on teste juste que le mécanisme de vérification d'adresse fonctionne
        
        $message = 'test message';
        $signature = base64_encode(random_bytes(64));
        $pubKey = base64_encode(str_repeat('x', 33));
        $wrongAddress = 'ki1wrongaddress';

        $result = $this->service->verifySignature($message, $signature, $pubKey, $wrongAddress);

        $this->assertFalse($result);
    }

    /**
     * Test d'intégration avec une vraie signature Cosmos
     * Note: Ce test nécessiterait une vraie paire de clés et signature Cosmos
     * Pour l'instant, c'est un placeholder qui montre comment le tester
     */
    public function testVerifySignatureIntegration(): void
    {
        // TODO: Ajouter un test avec une vraie signature Cosmos quand disponible
        // Exemple:
        // $message = "I authorize the migration of my XKI tokens from ki1... to 0x... Nonce: abc. Timestamp: 1234567890";
        // $signature = "base64_encoded_real_signature";
        // $pubKey = "base64_encoded_real_pubkey";
        // $address = "ki1qz5sd99vrwla22zr6xm4d0lzm63z5cs3rr3krh";
        // $result = $this->service->verifySignature($message, $signature, $pubKey, $address);
        // $this->assertTrue($result);

        $this->markTestIncomplete('Integration test with real Cosmos signature needed');
    }

    public function testServiceCanBeInstantiated(): void
    {
        $service = new CosmosSignatureService();
        $this->assertInstanceOf(CosmosSignatureService::class, $service);
    }
}
