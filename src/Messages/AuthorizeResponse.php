<?php
/**
 * Masterpass Authorize AbstractResponse
 */

namespace Omnipay\Masterpass\Messages;

use Exception;
use RuntimeException;

class AuthorizeResponse extends AbstractResponse
{
    /**
     * @return string
     */
    public function getToken(): string
    {
        $phone = $this->createValidPHone();
        $dateTime = date('YmdHis');
        $data = 'FF01' . $this->specPadLen($this->getClientId()) . $this->specToBHex($this->getClientId())
            . 'FF02' . '01' . $this->getTimezone()
            . 'FF03' . $this->specPadLen($dateTime) . $this->specToBHex($dateTime)
            . 'FF04' . $this->specPadLen($phone) . $this->specToBHex($phone)
            . 'FF05' . $this->specPadLen($this->getTransactionReference()) . $this->specToBHex($this->getTransactionReference())
            . 'FF06' . $this->specPadLen($this->getUserId()) . $this->specToBHex($this->getUserId())
            . 'FF07' . '01' . $this->getValidatedMsisdn()
            . 'FF08' . '01' . $this->getValidationType()
            . 'FF09' . '01' . $this->getMerchantType();

        if (strlen($data) % 32 !== 0) {
            $data .= '8';
            $padC = ceil(strlen($data) / 32) * 32;
            $data = str_pad($data, $padC, '0', STR_PAD_RIGHT);
        }

        return $this->prepareToken($data);
    }

    public function getData()
    {
        return ['masterPassToken' => $this->isSuccessful() ? $this->getToken() : ''];
    }

    public function getTransactionReference()
    {
        return $this->data['reference_number'] ?? null;
    }

    /**
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return $this->data['client_id'] ?? null;
    }

    /**
     * @return string|null
     */
    public function getUserId(): ?string
    {
        return $this->data['user_id'] ?? null;
    }

    /**
     * @return string|null
     */
    public function getEncKey(): ?string
    {
        return $this->data['encryption_key'] ?? null;
    }

    /**
     * @return string|null
     */
    public function getMacKey(): ?string
    {
        return $this->data['mac_key'] ?? null;
    }

    /**
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->data['phone'] ?? null;
    }

    /**
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->data['timezone'];
    }

    /**
     * @return string
     */
    public function getValidationType(): string
    {
        return $this->data['validationType'] ?? '00';
    }

    /**
     * @return string
     */
    public function getMerchantType(): string
    {
        return $this->data['merchantType'] ?? '00';
    }

    /**
     * @return string
     */
    public function getValidatedMsisdn(): string
    {
        return $this->data['validatedMsisdn'] ?? '00';
    }

    /**
     * @param string $value
     * @param int $pad
     * @return string
     */
    private function specPadLen(string $value, int $pad = 2): string
    {
        $len = strlen($value);
        $dLen = strtoupper(dechex($len));
        return str_pad($dLen, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * @param string $str
     * @return string
     */
    private function specToBHex(string $str): string
    {
        return strtoupper(bin2hex($str));
    }

    /**
     * @return string
     */
    private function createValidPHone(): string
    {
        $phone = preg_replace('/[^0-9]/', '', $this->getPhone());

        if (strpos($phone, '00') === 0) {
            $phone = substr($phone, 2);
        } elseif (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }

        if (strlen($phone) === 10) {
            return '90' . $phone;
        }

        return $phone;
    }

    /**
     * @param string $data
     * @return string
     */
    private function prepareToken(string $data): string
    {
        try {
            $iv = '00000000000000000000000000000000';
            $iv = pack('H*', $iv);
            $options = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING;
        } catch (Exception $exception) {
            throw new RuntimeException($exception);
        }
        $encKey = $this->getEncKey();
        $encKey = pack('H*', $encKey);
        $packData = pack('H*', $data);
        $encryptData = openssl_encrypt($packData, 'aes-128-cbc', $encKey, $options, $iv);
        $encryptData2 = strtoupper(bin2hex($encryptData));
        $macKey = hash_hmac('SHA1', $encryptData2, $this->getMacKey());

        return $encryptData2 . strtoupper($macKey);
    }
}
