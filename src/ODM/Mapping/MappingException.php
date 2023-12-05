<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\AbstractDocument;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Index;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\MappingException as BaseMappingException;
use ReflectionException;
use ReflectionObject;
use function sprintf;

final class MappingException extends BaseMappingException
{
    public static function cascadeOnEmbeddedNotAllowed(string $className, string $fieldName): self
    {
        return new self(sprintf('Cascade on %s::%s is not allowed.', $className, $fieldName));
    }

    public static function classCanOnlyBeMappedByOneAbstractDocument(
        string $className,
        AbstractDocument $mappedAs,
        AbstractDocument $offending
    ): self {
        return new self(
            sprintf(
                "Can not map class '%s' as %s because it was already mapped as %s.",
                $className,
                (new ReflectionObject($offending))->getShortName(),
                (new ReflectionObject($mappedAs))->getShortName(),
            )
        );
    }

    public static function classIsNotAValidDocument(string $className): self
    {
        return new self(sprintf('Class %s is not a valid document or mapped super class.', $className));
    }

    public static function collectionClassDoesNotImplementCommonInterface(
        string $className,
        string $fieldName,
        string $collectionClass
    ): self {
        return new self(
            sprintf(
                '%s used as custom collection class for %s::%s has to implement %s interface.',
                $collectionClass,
                $className,
                $fieldName,
                Collection::class
            )
        );
    }

    public static function duplicateDatabaseFieldName(
        string $document,
        string $offendingFieldName,
        string $databaseName,
        string $originalFieldName
    ): self {
        return new self(
            sprintf(
                'Field "%s" in class "%s" is mapped to field "%s" in the database, but that name is already in use by field "%s".',
                $offendingFieldName,
                $document,
                $databaseName,
                $originalFieldName
            )
        );
    }

    public static function identifierRequired(string $documentName): self
    {
        return new self(
            sprintf(
                "No identifier/primary key specified for Document '%s'. Every Document must have an identifier/primary key.",
                $documentName
            )
        );
    }

    public static function invalidDocumentIndex(string $property): self
    {
        return new self(sprintf('%s must be array of %s.', $property, Index::class));
    }

    public static function invalidRepositoryClass(
        string $className,
        string $repositoryClass,
        string $expectedRepositoryClass
    ): self {
        return new self(
            sprintf(
                'Invalid repository class "%s" for mapped class "%s". It must be an instance of "%s".',
                $repositoryClass,
                $className,
                $expectedRepositoryClass,
            )
        );
    }

    public static function mappingNotFound(string $className, string $fieldName): self
    {
        return new self(sprintf("No mapping found for field '%s' in class '%s'.", $fieldName, $className));
    }

    public static function mappingNotFoundByDbName(string $className, string $dbFieldName): self
    {
        return new self(sprintf("No mapping found for field by DB name '%s' in class '%s'.", $dbFieldName, $className));
    }

    public static function missingFieldName(string $className): self
    {
        return new self(sprintf("The Document class '%s' field mapping misses the 'fieldName' attribute.", $className));
    }

    public static function mustNotChangeIdentifierFieldsType(string $className, string $fieldName): self
    {
        return new self(sprintf('%s::%s was declared an identifier and must stay this way.', $className, $fieldName));
    }

    public static function nonBackedEnumMapped(string $className, string $fieldName, string $enumType): self
    {
        return new self(
            sprintf(
                'Attempting to map a non-backed enum %s: %s::%s',
                $enumType,
                $className,
                $fieldName,
            )
        );
    }

    public static function nonEnumTypeMapped(string $className, string $fieldName, string $enumType): self
    {
        return new self(
            sprintf(
                'Attempting to map a non-enum type %s as an enum: %s::%s',
                $enumType,
                $className,
                $fieldName,
            )
        );
    }

    public static function owningAndInverseReferencesRequireTargetDocument(string $className, string $fieldName): self
    {
        return new self(
            sprintf(
                'Target document must be specified for owning/inverse sides of reference: %s::%s',
                $className,
                $fieldName
            )
        );
    }

    public static function reflectionFailure(string $document, ReflectionException $previousException): self
    {
        return new self('An error occurred in '.$document, 0, $previousException);
    }

    public static function simpleReferenceRequiresTargetDocument(string $className, string $fieldName): self
    {
        return new self(
            sprintf('Target document must be specified for identifier reference: %s::%s', $className, $fieldName)
        );
    }

    public static function typeExists(string $name): self
    {
        return new self(sprintf('Type %s already exists.', $name));
    }

    public static function typeNotFound(string $name): self
    {
        return new self(sprintf('Type to be overwritten %s does not exist.', $name));
    }
}
