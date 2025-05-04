<?php

namespace Sybil\Event\Traits;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Exception\ValidationException;
use Sybil\Exception\AuthenticationException;
use Sybil\Utility\Validation\EventValidator;
use Sybil\Utility\Security\DataSanitizer;
use Sybil\Utility\Security\SignatureVerifier;
use Sybil\Utility\Security\KeyValidator;
use Sybil\Utility\Log\LoggerFactory;

trait EventValidationTrait
{
    /**
     * @var LoggerInterface Logger instance
     */
    protected LoggerInterface $logger;

    /**
     * @var EventValidator Event validator
     */
    protected EventValidator $validator;

    /**
     * @var DataSanitizer Data sanitizer
     */
    protected DataSanitizer $sanitizer;

    /**
     * @var SignatureVerifier Signature verifier
     */
    protected SignatureVerifier $signatureVerifier;

    /**
     * @var KeyValidator Key validator
     */
    protected KeyValidator $keyValidator;

    /**
     * Initialize the trait
     */
    protected function initializeEventValidation(?LoggerInterface $logger = null): void
    {
        $this->logger = $logger ?? new NullLogger();
        $this->validator = new EventValidator($this->logger);
        $this->sanitizer = new DataSanitizer($this->logger);
        $this->signatureVerifier = new SignatureVerifier($this->logger);
        $this->keyValidator = new KeyValidator($this->logger);
    }

    /**
     * Validate the event data
     * 
     * @return bool True if validation passes
     * @throws ValidationException If validation fails
     * @throws AuthenticationException If authentication validation fails
     */
    protected function validateBasicFields(): bool
    {
        $this->logger->debug('Validating basic event fields');

        try {
            $errors = [];

            // Validate content
            if (empty($this->content)) {
                $errors['content'] = 'Content cannot be empty';
            } else {
                $this->validateStringLength('content', $this->content, 65535);
            }

            // Validate kind
            if (empty($this->kind)) {
                $errors['kind'] = 'Event kind cannot be empty';
            } elseif (!is_numeric($this->kind)) {
                $errors['kind'] = 'Event kind must be numeric';
            }

            // Validate pubkey
            if (empty($this->pubkey)) {
                $errors['pubkey'] = 'Public key cannot be empty';
            } elseif (!$this->keyValidator->validatePublicKey($this->pubkey)) {
                $errors['pubkey'] = 'Invalid public key format';
            }

            // Validate signature if present
            if (!empty($this->sig)) {
                if (!$this->signatureVerifier->verify($this)) {
                    throw new AuthenticationException(
                        'Invalid event signature',
                        AuthenticationException::ERROR_SIGNATURE_VERIFICATION
                    );
                }
            }

            if (!empty($errors)) {
                $this->logger->error('Basic field validation failed', ['errors' => $errors]);
                throw new ValidationException($errors);
            }

            $this->logger->info('Basic field validation passed');
            return true;
        } catch (AuthenticationException $e) {
            $this->logger->error('Authentication validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (ValidationException $e) {
            $this->logger->error('Basic field validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during validation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidationException([
                'general' => 'Unexpected error during validation: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Validate string length
     * 
     * @param string $field The field name
     * @param string $value The value to validate
     * @param int $maxLength Maximum allowed length
     * @return bool True if validation passes
     * @throws ValidationException If validation fails
     */
    protected function validateStringLength(string $field, string $value, int $maxLength): bool
    {
        $this->logger->debug('Validating string length', [
            'field' => $field,
            'length' => strlen($value),
            'max_length' => $maxLength
        ]);

        try {
            if (strlen($value) > $maxLength) {
                $error = sprintf('%s exceeds maximum length of %d characters', $field, $maxLength);
                $this->logger->error('String length validation failed', [
                    'field' => $field,
                    'length' => strlen($value),
                    'max_length' => $maxLength,
                    'error' => $error
                ]);
                throw new ValidationException([$field => $error]);
            }

            $this->logger->info('String length validation passed', [
                'field' => $field,
                'length' => strlen($value)
            ]);
            return true;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during string length validation', [
                'field' => $field,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidationException([
                $field => 'Unexpected error during validation: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Validate event tags
     * 
     * @return bool True if validation passes
     * @throws ValidationException If validation fails
     */
    protected function validateTags(): bool
    {
        $this->logger->debug('Validating event tags');

        try {
            if (!is_array($this->tags)) {
                throw new ValidationException(['tags' => 'Tags must be an array']);
            }

            foreach ($this->tags as $index => $tag) {
                if (!is_array($tag)) {
                    throw new ValidationException([
                        'tags' => sprintf('Tag at index %d must be an array', $index)
                    ]);
                }

                if (empty($tag[0])) {
                    throw new ValidationException([
                        'tags' => sprintf('Tag name at index %d cannot be empty', $index)
                    ]);
                }

                $this->validateStringLength('tag_name', $tag[0], 255);
            }

            $this->logger->info('Tag validation passed', ['tag_count' => count($this->tags)]);
            return true;
        } catch (ValidationException $e) {
            $this->logger->error('Tag validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during tag validation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidationException([
                'tags' => 'Unexpected error during validation: ' . $e->getMessage()
            ]);
        }
    }
} 