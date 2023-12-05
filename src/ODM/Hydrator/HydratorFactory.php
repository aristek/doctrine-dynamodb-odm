<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Hydrator;

use Doctrine\Common\EventManager;
use Doctrine\Persistence\Mapping\MappingException;
use ProxyManager\Proxy\GhostObjectInterface;
use ReflectionException;
use Aristek\Bundle\DynamodbBundle\ODM\Configuration;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Event\LifecycleEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\Event\PreLoadEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\Events;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Types\Type;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
use function array_key_exists;
use function chmod;
use function class_exists;
use function dirname;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_writable;
use function mkdir;
use function rename;
use function rtrim;
use function sprintf;
use function str_replace;
use function substr;
use function uniqid;
use const DIRECTORY_SEPARATOR;

/**
 * The HydratorFactory class is responsible for instantiating a correct hydrator type based on document's ClassMetadata
 */
final class HydratorFactory
{
    /**
     * Which algorithm to use to automatically (re)generate hydrator classes.
     */
    private int $autoGenerate;

    /**
     * The DocumentManager this factory is bound to.
     */
    private DocumentManager $dm;

    /**
     * The EventManager associated with this Hydrator
     */
    private EventManager $evm;

    /**
     * The directory that contains all hydrator classes.
     */
    private string $hydratorDir;

    /**
     * The namespace that contains all hydrator classes.
     */
    private ?string $hydratorNamespace;

    /**
     * Array of instantiated document hydrators.
     */
    private array $hydrators = [];

    /**
     * The UnitOfWork used to coordinate object-level transactions.
     */
    private ?UnitOfWork $unitOfWork = null;

    /**
     * @throws HydratorException
     */
    public function __construct(
        DocumentManager $dm,
        EventManager $evm,
        ?string $hydratorDir,
        ?string $hydratorNs,
        int $autoGenerate
    ) {
        if (!$hydratorDir) {
            throw HydratorException::hydratorDirectoryRequired();
        }

        if (!$hydratorNs) {
            throw HydratorException::hydratorNamespaceRequired();
        }

        $this->dm = $dm;
        $this->evm = $evm;
        $this->hydratorDir = $hydratorDir;
        $this->hydratorNamespace = $hydratorNs;
        $this->autoGenerate = $autoGenerate;
    }

    /**
     * Generates hydrator classes for all given classes.
     *
     * @param ClassMetadata<object>[] $classes The classes (ClassMetadata instances) for which to generate hydrators.
     * @param string|null             $toDir   The target directory of the hydrator classes. If not specified, the
     *                                         directory configured on the Configuration of the DocumentManager used
     *                                         by this factory is used.
     *
     * @throws HydratorException
     */
    public function generateHydratorClasses(array $classes, ?string $toDir = null): void
    {
        $hydratorDir = $toDir ?: $this->hydratorDir;
        $hydratorDir = rtrim($hydratorDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        foreach ($classes as $class) {
            $hydratorClassName = str_replace('\\', '', $class->name).'Hydrator';
            $hydratorFileName = $hydratorDir.$hydratorClassName.'.php';
            $this->generateHydratorClass($class, $hydratorClassName, $hydratorFileName);
        }
    }

    /**
     * Gets the hydrator object for the given document class.
     *
     * @throws HydratorException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getHydratorFor(string $className): HydratorInterface
    {
        if (isset($this->hydrators[$className])) {
            return $this->hydrators[$className];
        }

        $hydratorClassName = str_replace('\\', '', $className).'Hydrator';
        $fqn = $this->hydratorNamespace.'\\'.$hydratorClassName;
        $class = $this->dm->getClassMetadata($className);

        if (!class_exists($fqn, false)) {
            $fileName = $this->hydratorDir.DIRECTORY_SEPARATOR.$hydratorClassName.'.php';
            switch ($this->autoGenerate) {
                case Configuration::AUTOGENERATE_NEVER:
                    require $fileName;
                    break;

                case Configuration::AUTOGENERATE_ALWAYS:
                    $this->generateHydratorClass($class, $hydratorClassName, $fileName);
                    require $fileName;
                    break;

                case Configuration::AUTOGENERATE_FILE_NOT_EXISTS:
                    if (!file_exists($fileName)) {
                        $this->generateHydratorClass($class, $hydratorClassName, $fileName);
                    }

                    require $fileName;
                    break;

                case Configuration::AUTOGENERATE_EVAL:
                    $this->generateHydratorClass($class, $hydratorClassName, null);
                    break;
            }
        }

        $this->hydrators[$className] = new $fqn($this->dm, $this->unitOfWork, $class);

        return $this->hydrators[$className];
    }

    /**
     * Hydrate array of DynamoDB document data into the given document object.
     *
     * @throws HydratorException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function hydrate(object $document, array $data, array $hints = []): array
    {
        $metadata = $this->dm->getClassMetadata($document::class);
        // Invoke preLoad lifecycle events and listeners
        if (!empty($metadata->lifecycleCallbacks[Events::preLoad])) {
            $args = [new PreLoadEventArgs($document, $this->dm, $data)];
            $metadata->invokeLifecycleCallbacks(Events::preLoad, $document, $args);
        }

        $this->evm->dispatchEvent(Events::preLoad, new PreLoadEventArgs($document, $this->dm, $data));

        // alsoLoadMethods may transform the document before hydration
        if (!empty($metadata->alsoLoadMethods)) {
            foreach ($metadata->alsoLoadMethods as $method => $fieldNames) {
                foreach ($fieldNames as $fieldName) {
                    // Invoke the method only once for the first field we find
                    if (array_key_exists($fieldName, $data)) {
                        $document->$method($data[$fieldName]);
                        continue 2;
                    }
                }
            }
        }

        if ($document instanceof GhostObjectInterface && $document->getProxyInitializer() !== null) {
            // Inject an empty initialiser to not load any object data
            $document->setProxyInitializer(static function (
                GhostObjectInterface $ghostObject,
                string $method, // we don't care
                array $parameters, // we don't care
                &$initializer,
                array $properties // we currently do not use this
            ): bool {
                $initializer = null;

                return true;
            });
        }

        $data = $this->getHydratorFor($metadata->name)->hydrate($document, $data, $hints);

        // Invoke the postLoad lifecycle callbacks and listeners
        if (!empty($metadata->lifecycleCallbacks[Events::postLoad])) {
            $metadata->invokeLifecycleCallbacks(
                Events::postLoad,
                $document,
                [new LifecycleEventArgs($document, $this->dm)]
            );
        }

        $this->evm->dispatchEvent(Events::postLoad, new LifecycleEventArgs($document, $this->dm));

        return $data;
    }

    /**
     * Sets the UnitOfWork instance.
     *
     * @internal
     */
    public function setUnitOfWork(UnitOfWork $uow): void
    {
        $this->unitOfWork = $uow;
    }

    /**
     * @throws HydratorException
     */
    private function generateHydratorClass(ClassMetadata $class, string $hydratorClassName, ?string $fileName): void
    {
        $code = '';

        foreach ($class->fieldMappings as $fieldName => $mapping) {
            if (isset($mapping['alsoLoadFields'])) {
                foreach ($mapping['alsoLoadFields'] as $name) {
                    $code .= sprintf(
                        <<<EOF

        /** @AlsoLoad("$name") */
        if (!array_key_exists('%1\$s', \$data) && array_key_exists('$name', \$data)) {
            \$data['%1\$s'] = \$data['$name'];
        }

EOF
                        ,
                        $mapping['name'],
                    );
                }
            }

            if ($mapping['type'] === 'date') {
                $code .= sprintf(
                    <<<'EOF'

        /** @Field(type="date") */
        if (isset($data['%1$s'])) {
            $value = $data['%1$s'];
            %3$s
            $this->class->reflFields['%2$s']->setValue($document, clone $return);
            $hydratedData['%2$s'] = $return;
        }

EOF
                    ,
                    $mapping['name'],
                    $mapping['fieldName'],
                    Type::getType($mapping['type'])->closureToPHP(),
                );
            } elseif (!isset($mapping['association'])) {
                $code .= sprintf(
                    <<<EOF

        /** @Field(type="{$mapping['type']}") */
        if (isset(\$data['%1\$s']) || (! empty(\$this->class->fieldMappings['%2\$s']['nullable']) && array_key_exists('%1\$s', \$data))) {
            \$value = \$data['%1\$s'];
            if (\$value !== null) {
                \$typeIdentifier = \$this->class->fieldMappings['%2\$s']['type'];
                %3\$s
            } else {
                \$return = null;
            }
            \$this->class->reflFields['%2\$s']->setValue(\$document, \$return);
            \$hydratedData['%2\$s'] = \$return;
        }

EOF
                    ,
                    $mapping['name'],
                    $mapping['fieldName'],
                    Type::getType($mapping['type'])->closureToPHP(),
                );
            } elseif ($mapping['association'] === ClassMetadata::REFERENCE_ONE && $mapping['isOwningSide']) {
                $code .= sprintf(
                    <<<'EOF'

        /** @ReferenceOne */
        if (isset($data['%1$s']) || (! empty($this->class->fieldMappings['%2$s']['nullable']) && array_key_exists('%1$s', $data))) {
            $return = $data['%1$s'];
            if ($return !== null) {
                $className = $this->unitOfWork->getClassNameForAssociation($this->class->fieldMappings['%2$s'], $return);
                $identifier = $return;
                $targetMetadata = $this->dm->getClassMetadata($className);
                $id = $targetMetadata->getPHPIdentifierValue($identifier);
                $return = $this->dm->getReference($className, $id);
            }

            $this->class->reflFields['%2$s']->setValue($document, $return);
            $hydratedData['%2$s'] = $return;
        }

EOF
                    ,
                    $mapping['name'],
                    $mapping['fieldName'],
                    $class->getName(),
                );
            } elseif ($mapping['association'] === ClassMetadata::REFERENCE_ONE && $mapping['isInverseSide']) {
                if (isset($mapping['repositoryMethod']) && $mapping['repositoryMethod']) {
                    $code .= sprintf(
                        <<<'EOF'

        $className = $this->class->fieldMappings['%2$s']['targetDocument'];
        $return = $this->dm->getRepository($className)->%3$s($document);
        $this->class->reflFields['%2$s']->setValue($document, $return);
        $hydratedData['%2$s'] = $return;

EOF
                        ,
                        $mapping['name'],
                        $mapping['fieldName'],
                        $mapping['repositoryMethod'],
                    );
                } else {
                    $code .= sprintf(
                        <<<'EOF'

        $mapping = $this->class->fieldMappings['%2$s'];
        $className = $mapping['targetDocument'];
        $targetClass = $this->dm->getClassMetadata($mapping['targetDocument']);
        $mappedByFieldName = mapping['mappedBy'];
        $criteria = array_merge(array($mappedByFieldName => $data['_id']), array());

        $return = $this->unitOfWork->getDocumentPersister($className)->load($criteria, null, array());
        $this->class->reflFields['%2$s']->setValue($document, $return);
        $hydratedData['%2$s'] = $return;

EOF
                        ,
                        $mapping['name'],
                        $mapping['fieldName'],
                    );
                }
            } elseif ($mapping['association'] === ClassMetadata::REFERENCE_MANY || $mapping['association'] === ClassMetadata::EMBED_MANY) {
                $code .= sprintf(
                    <<<'EOF'

        /** @Many */
        $dynamoData = isset($data['%1$s']) ? $data['%1$s'] : null;

        if ($dynamoData !== null && ! is_array($dynamoData)) {
            throw HydratorException::associationTypeMismatch('%3$s', '%1$s', 'array', gettype($dynamoData));
        }

        $return = $this->dm->getConfiguration()->getPersistentCollectionFactory()->create($this->dm, $this->class->fieldMappings['%2$s']);
        $return->setHints($hints);
        $return->setOwner($document, $this->class->fieldMappings['%2$s']);
        $return->setInitialized(false);
        if ($dynamoData) {
            $return->setDynamoData($dynamoData);
        }
        $this->class->reflFields['%2$s']->setValue($document, $return);
        $hydratedData['%2$s'] = $return;

EOF
                    ,
                    $mapping['name'],
                    $mapping['fieldName'],
                    $class->getName(),
                );
            } elseif ($mapping['association'] === ClassMetadata::EMBED_ONE) {
                $code .= sprintf(
                    <<<'EOF'

        /** @EmbedOne */
        if (isset($data['%1$s']) || (! empty($this->class->fieldMappings['%2$s']['nullable']) && array_key_exists('%1$s', $data))) {
            $return = $data['%1$s'];
            if ($return !== null) {
                $embeddedDocument = $return;

                if (! is_array($embeddedDocument)) {
                    throw HydratorException::associationTypeMismatch('%3$s', '%1$s', 'array', gettype($embeddedDocument));
                }

                $className = $this->unitOfWork->getClassNameForAssociation($this->class->fieldMappings['%2$s'], $embeddedDocument);
                $embeddedMetadata = $this->dm->getClassMetadata($className);
                $return = $embeddedMetadata->newInstance();

                $this->unitOfWork->setParentAssociation($return, $this->class->fieldMappings['%2$s'], $document, '%1$s');

                $embeddedData = $this->dm->getHydratorFactory()->hydrate($return, $embeddedDocument, $hints);
                $embeddedId = $embeddedMetadata->identifier && isset($embeddedData[$embeddedMetadata->identifier]) ? $embeddedData[$embeddedMetadata->identifier] : null;

                if (empty($hints[Query::HINT_READ_ONLY])) {
                    $this->unitOfWork->registerManaged($return, $embeddedId, $embeddedData);
                }
            }

            $this->class->reflFields['%2$s']->setValue($document, $return);
            $hydratedData['%2$s'] = $return;
        }

EOF
                    ,
                    $mapping['name'],
                    $mapping['fieldName'],
                    $class->getName(),
                );
            }
        }

        $namespace = $this->hydratorNamespace;
        $code = sprintf(
            <<<EOF
<?php

namespace $namespace;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorException;
use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorInterface;
use Aristek\Bundle\DynamodbBundle\ODM\Query\Query;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ODM. DO NOT EDIT THIS FILE.
 */
class $hydratorClassName implements HydratorInterface
{
    private \$dm;
    private \$unitOfWork;
    private \$class;

    public function __construct(DocumentManager \$dm, UnitOfWork \$uow, ClassMetadata \$class)
    {
        \$this->dm = \$dm;
        \$this->unitOfWork = \$uow;
        \$this->class = \$class;
    }

    public function hydrate(object \$document, array \$data, array \$hints = array()): array
    {
        \$hydratedData = array();
%s        return \$hydratedData;
    }
}
EOF
            ,
            $code,
        );

        if ($fileName === null) {
            if (!class_exists($namespace.'\\'.$hydratorClassName)) {
                eval(substr($code, 5));
            }

            return;
        }

        $parentDirectory = dirname($fileName);

        if (!is_dir($parentDirectory) && (@mkdir($parentDirectory, 0775, true) === false)) {
            throw HydratorException::hydratorDirectoryNotWritable();
        }

        if (!is_writable($parentDirectory)) {
            throw HydratorException::hydratorDirectoryNotWritable();
        }

        $tmpFileName = $fileName.'.'.uniqid('', true);
        file_put_contents($tmpFileName, $code);
        rename($tmpFileName, $fileName);
        chmod($fileName, 0664);
    }
}
