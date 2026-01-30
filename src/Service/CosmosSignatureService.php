<?php

namespace App\Service;

use Elliptic\EC;
use BitWasp\Bech32\Bech32;

/**
 * Service de vérification de signatures Cosmos (secp256k1)
 * Compatible avec les adresses Ki Chain (ki1...)
 * Supporte le format ADR-036 utilisé par Keplr signArbitrary
 */
class CosmosSignatureService
{
    private EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    /**
     * Vérifie qu'une signature Cosmos ADR-036 est valide et correspond à l'adresse attendue
     * Format utilisé par Keplr signArbitrary()
     *
     * @param string $message Le message original qui a été signé
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

            // Dérive l'adresse depuis la pubKey et compare d'abord
            $derivedAddress = $this->deriveAddress($pubKeyBytes);
            if ($derivedAddress !== $expectedAddress) {
                return false;
            }

            // Extrait r et s de la signature (32 bytes chacun)
            $r = bin2hex(substr($signatureBytes, 0, 32));
            $s = bin2hex(substr($signatureBytes, 32, 32));

            // Construit le document de signature ADR-036 (format Keplr signArbitrary)
            $signDoc = $this->buildAdr036SignDoc($expectedAddress, $message);
            
            // Hash SHA256 du document de signature sérialisé
            $msgHash = hash('sha256', $signDoc, false);

            // Import de la clé publique
            $pubKeyHex = bin2hex($pubKeyBytes);
            $key = $this->ec->keyFromPublic($pubKeyHex, 'hex');

            // Vérifie la signature
            return $key->verify($msgHash, ['r' => $r, 's' => $s]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Construit le document de signature ADR-036 au format JSON canonique
     * C'est ce format que Keplr signe réellement avec signArbitrary()
     *
     * @param string $signer L'adresse du signataire
     * @param string $data Le message à signer
     * @return string Le JSON sérialisé de manière canonique (clés triées, pas d'espaces)
     */
    private function buildAdr036SignDoc(string $signer, string $data): string
    {
        // Le document doit être sérialisé avec les clés dans l'ordre alphabétique
        // et sans espaces (format canonique)
        $doc = [
            'account_number' => '0',
            'chain_id' => '',
            'fee' => [
                'amount' => [],
                'gas' => '0'
            ],
            'memo' => '',
            'msgs' => [
                [
                    'type' => 'sign/MsgSignData',
                    'value' => [
                        'data' => base64_encode($data),
                        'signer' => $signer
                    ]
                ]
            ],
            'sequence' => '0'
        ];

        // JSON_UNESCAPED_SLASHES est important pour la compatibilité
        return json_encode($doc, JSON_UNESCAPED_SLASHES);
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
}
