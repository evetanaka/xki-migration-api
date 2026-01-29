<?php

namespace App\Service;

use Elliptic\EC;
use BitWasp\Bech32\Bech32;

/**
 * Service de vérification de signatures Cosmos (secp256k1)
 * Compatible avec les adresses Ki Chain (ki1...)
 */
class CosmosSignatureService
{
    private EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    /**
     * Vérifie qu'une signature Cosmos est valide et correspond à l'adresse attendue
     *
     * @param string $message Le message qui a été signé
     * @param string $signature La signature en base64
     * @param string $pubKey La clé publique en base64
     * @param string $expectedAddress L'adresse attendue (format ki1...)
     * @return bool True si la signature est valide et l'adresse correspond
     */
    public function verifySignature(
        string $message,
        string $signature,
        string $pubKey,
        string $expectedAddress
    ): bool {
        try {
            // Décode la signature base64
            $signatureBytes = base64_decode($signature, true);
            if ($signatureBytes === false) {
                return false;
            }

            // Décode la pubKey base64
            $pubKeyBytes = base64_decode($pubKey, true);
            if ($pubKeyBytes === false) {
                return false;
            }

            // Vérifie que la signature a la bonne longueur (64 bytes pour secp256k1)
            if (strlen($signatureBytes) !== 64) {
                return false;
            }

            // Extrait r et s de la signature (32 bytes chacun)
            $r = bin2hex(substr($signatureBytes, 0, 32));
            $s = bin2hex(substr($signatureBytes, 32, 32));

            // Hash du message (SHA256)
            $msgHash = hash('sha256', $message, false);

            // Import de la clé publique
            $pubKeyHex = bin2hex($pubKeyBytes);
            $key = $this->ec->keyFromPublic($pubKeyHex, 'hex');

            // Vérifie la signature
            $isValid = $key->verify($msgHash, ['r' => $r, 's' => $s]);

            if (!$isValid) {
                return false;
            }

            // Dérive l'adresse depuis la pubKey et compare
            $derivedAddress = $this->deriveAddress($pubKeyBytes);
            
            return $derivedAddress === $expectedAddress;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Dérive une adresse Ki Chain (ki1...) depuis une clé publique
     *
     * @param string $pubKeyBytes Les bytes de la clé publique
     * @return string L'adresse au format bech32 (ki1...)
     */
    private function deriveAddress(string $pubKeyBytes): string
    {
        // SHA256 de la clé publique
        $sha256Hash = hash('sha256', $pubKeyBytes, true);

        // RIPEMD160 du hash SHA256
        $ripemd160Hash = hash('ripemd160', $sha256Hash, true);

        // Convertit en tableau d'entiers 5-bit pour bech32
        $words = $this->convertBits(array_values(unpack('C*', $ripemd160Hash)), 8, 5);

        // Encode en bech32 avec le préfixe 'ki'
        return Bech32::encode('ki', $words);
    }

    /**
     * Convertit un tableau de bits d'une base à une autre
     * Nécessaire pour l'encodage bech32
     *
     * @param array $data Données en entrée
     * @param int $fromBits Nombre de bits en entrée
     * @param int $toBits Nombre de bits en sortie
     * @param bool $pad Ajouter du padding si nécessaire
     * @return array Données converties
     */
    private function convertBits(array $data, int $fromBits, int $toBits, bool $pad = true): array
    {
        $acc = 0;
        $bits = 0;
        $result = [];
        $maxv = (1 << $toBits) - 1;

        foreach ($data as $value) {
            $acc = ($acc << $fromBits) | $value;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $result[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad && $bits > 0) {
            $result[] = ($acc << ($toBits - $bits)) & $maxv;
        } elseif (!$pad && $bits >= $fromBits) {
            throw new \Exception('Invalid bits');
        } elseif (!$pad && (($acc << ($toBits - $bits)) & $maxv)) {
            throw new \Exception('Invalid padding');
        }

        return $result;
    }

    /**
     * Génère le message de claim pour la migration
     *
     * @param string $kiAddress L'adresse Ki Chain de l'utilisateur
     * @param string $ethAddress L'adresse Ethereum de destination
     * @param string $nonce Le nonce unique pour cette migration
     * @return string Le message à signer
     */
    public function generateClaimMessage(
        string $kiAddress,
        string $ethAddress,
        string $nonce
    ): string {
        $timestamp = time();
        
        return sprintf(
            "I authorize the migration of my XKI tokens from %s to %s. Nonce: %s. Timestamp: %d",
            $kiAddress,
            $ethAddress,
            $nonce,
            $timestamp
        );
    }
}
