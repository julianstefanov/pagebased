<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\Domain\Model\Demand;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\ColumnMap;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use Zeroseven\Rampage\Exception\PropertyException;
use Zeroseven\Rampage\Exception\TypeException;
use Zeroseven\Rampage\Exception\ValueException;
use Zeroseven\Rampage\Registration\AbstractObjectRegistration;
use Zeroseven\Rampage\Utility\CastUtility;

abstract class AbstractDemand implements DemandInterface
{
    public const PARAMETER_UID_LIST = '_id';
    public const PARAMETER_ORDER_BY = '_sorting';

    /** @var DemandProperty[] */
    protected array $properties = [];
    protected ?DataMap $dataMap = null;
    protected ?array $tableDefinition = null;

    /** @throws TypeException | Exception | PropertyException */
    public function __construct(string $className, array $parameterArray = null)
    {
        try {
            $this->dataMap = GeneralUtility::makeInstance(DataMapper::class)->getDataMap($className);
        } catch (InvalidArgumentException $e) {
        }

        $this->initProperties();

        if ($parameterArray !== null) {
            $this->setProperties($parameterArray, true, false);
        }
    }

    public static function makeInstance(AbstractObjectRegistration $objectRegistration, array $arguments = null): DemandInterface
    {
        $objectClass = $objectRegistration->getClassName();
        $demandClass = $objectRegistration->getDemandClassName() ?? ObjectDemand::class;

        return GeneralUtility::makeInstance($demandClass, $objectClass, $arguments);
    }

    public function addProperty(string $name, string $type, string $extbasePropertyName = null): self
    {
        $this->properties[$name] = GeneralUtility::makeInstance(DemandProperty::class, $name, $type, null, $extbasePropertyName);

        return $this;
    }

    protected function initProperties(): void
    {
        $this->addProperty(self::PARAMETER_UID_LIST, DemandProperty::TYPE_ARRAY);
        $this->addProperty(self::PARAMETER_ORDER_BY, DemandProperty::TYPE_STRING);

        // Get properties from class
        if ($this->dataMap) {
            foreach (GeneralUtility::makeInstance(ReflectionClass::class, $this->dataMap->getClassName())->getProperties() ?? [] as $reflection) {
                $name = $reflection->getName();

                // Check if the property exists in the database and the type can be handled
                if (($columnMap = $this->dataMap->getColumnMap($name)) && $type = $this->getType($reflection, $columnMap)) {
                    $this->addProperty($name, $type);
                }
            }
        }
    }

    protected function getTableDefinition(): ?array
    {
        if ($this->tableDefinition === null && $this->dataMap) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->dataMap->getTableName());

            if ($schemaManager = $queryBuilder->getSchemaManager()) {
                $this->tableDefinition = $schemaManager->listTableColumns($this->dataMap->getTableName());
            }
        }

        return $this->tableDefinition;
    }

    protected function getType(ReflectionProperty $reflection, ColumnMap $columnMap): ?string
    {
        // The field must not be defined in table controls
        if ($ctrl = $GLOBALS['TCA'][$this->dataMap->getTableName()]['ctrl']) {
            $fieldName = $columnMap->getColumnName();

            if (
                'uid' === $fieldName ||
                'pid' === $fieldName ||
                ($ctrl['cruser_id'] ?? null) === $fieldName ||
                ($ctrl['descriptionColumn'] ?? null) === $fieldName ||
                ($ctrl['editlock'] ?? null) === $fieldName ||
                ($ctrl['enableColumns']['disabled'] ?? null) === $fieldName ||
                ($ctrl['enableColumns']['fe_group'] ?? null) === $fieldName ||
                ($ctrl['enableColumns']['endtime'] ?? null) === $fieldName ||
                ($ctrl['enableColumns']['starttime'] ?? null) === $fieldName ||
                ($ctrl['languageField'] ?? null) === $fieldName ||
                ($ctrl['origUid'] ?? null) === $fieldName ||
                ($ctrl['translationSource'] ?? null) === $fieldName ||
                ($ctrl['transOrigDiffSourceField'] ?? null) === $fieldName ||
                ($ctrl['transOrigPointerField'] ?? null) === $fieldName ||
                ($ctrl['type'] ?? null) === $fieldName
            ) {
                return null;
            }
        }

        // Get type by class reflection
        if ($reflectionType = $reflection->getType()) {
            if (in_array(($type = $reflectionType->getName()), [DemandProperty::TYPE_ARRAY, DemandProperty::TYPE_INTEGER, DemandProperty::TYPE_BOOLEAN, DemandProperty::TYPE_STRING], true)) {
                return $type;
            }

            if ($reflectionType->getName() === ObjectStorage::class) {
                return DemandProperty::TYPE_ARRAY;
            }
        }

        // Get type by column map
        if (in_array($columnMap->getTypeOfRelation(), [ColumnMap::RELATION_HAS_MANY, ColumnMap::RELATION_HAS_AND_BELONGS_TO_MANY], true)) {
            return DemandProperty::TYPE_ARRAY;
        }

        // Check table definition
        if (($tableDefinition = $this->getTableDefinition()) && ($column = $tableDefinition[$columnMap->getColumnName()] ?? null) && $type = $column->getType()) {
            if ($type->getName() === 'smallint') {
                return DemandProperty::TYPE_BOOLEAN;
            }

            if ($type->getBindingType() === 1) {
                return DemandProperty::TYPE_INTEGER;

            }
            if ($type->getBindingType() === 2) {
                return DemandProperty::TYPE_STRING;
            }
        }

        return null;
    }

    /** @throws PropertyException */
    public function getProperty(string $propertyName): DemandProperty
    {
        if ($property = $this->properties[$propertyName] ?? null) {
            return $property;
        }

        throw new PropertyException(sprintf('Undefined Property "%s".', $propertyName), 1678175372);
    }

    /** @return DemandProperty[] */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function hasProperty(string $propertyName): bool
    {
        return isset($this->properties[$propertyName]);
    }

    /** @throws TypeException | PropertyException */
    public function setProperty(string $propertyName, mixed $value, bool $toggle = null): self
    {
        if ($property = $this->properties[$propertyName] ?? null) {
            $toggle ? $property->toggleValue($value) : $property->setValue($value);
        } else {
            throw new PropertyException(sprintf('Property "%s" does not exists in %s', $propertyName, __CLASS__), 1676061710);
        }

        return $this;
    }

    /** @throws TypeException | PropertyException */
    public function toggleProperty(string $propertyName, mixed $value): self
    {
        return $this->setProperty($propertyName, $value, true);
    }

    /** @throws TypeException | PropertyException */
    public function setProperties(array $parameterArray, bool $ignoreEmptyValues = null, bool $toggle = null): self
    {
        foreach ($this->properties as $property) {
            if (isset($parameterArray[$property->getParameter()])) {
                if ($value = $parameterArray[$property->getParameter()] ?? null) {
                    $this->setProperty($property->getName(), $value, $toggle);
                } elseif ($ignoreEmptyValues === false) {
                    $this->properties[$property->getName()]->clear();
                }
            }
        }

        return $this;
    }

    /** @throws TypeException | PropertyException */
    public function toggleProperties(array $parameterArray, bool $ignoreEmptyValues = null): self
    {
        return $this->setProperties($parameterArray, $ignoreEmptyValues, true);
    }

    public function getParameterArray(bool $ignoreEmptyValues = null): array
    {
        $params = [];

        // Collect values in array
        foreach ($this->properties as $property) {
            $params[$property->getParameter()] = (string)$property;
        }

        // Return array with/without empty values
        return !$ignoreEmptyValues ? $params : array_filter($params);
    }

    /** @throws TypeException */
    public function getParameterDiff(array $base, array $protectedParameters = null): array
    {
        $result = [];

        foreach ($this->properties as $property) {
            $parameter = $property->getParameter();

            if (
                ($protectedParameters && in_array($parameter, $protectedParameters, true))
                || (
                    // TODO: use $proerty->parseValue() to compare values
                    ($property->isInteger() && CastUtility::int($base[$parameter] ?? 0) !== $property->getValue())
                    || ($property->isString() && CastUtility::string($base[$parameter] ?? '') !== $property->getValue())
                    || ($property->isBoolean() && CastUtility::bool($base[$parameter] ?? false) !== $property->getValue())
                    || ($property->isArray() && (count(array_diff(CastUtility::array($base[$parameter] ?? []), $property->getValue())) || count(array_diff($property->getValue(), CastUtility::array($base[$parameter] ?? [])))))
                )
            ) {
                if (!empty($property->getValue())) {
                    $result[$parameter] = $property->toString();
                } elseif (!empty($base[$parameter])) {
                    $result[$parameter] = '';
                }
            }
        }

        return $result;
    }

    public function clear(): self
    {
        foreach ($this->properties as $property) {
            $this->properties[$property->getName()]->clear();
        }

        return $this;
    }

    /** @throws PropertyException */
    public function getUidList(): array
    {
        return $this->getProperty(self::PARAMETER_UID_LIST)->getValue();
    }

    /** @throws TypeException | PropertyException */
    public function setUidList(mixed $value): self
    {
        $this->setProperty(self::PARAMETER_UID_LIST, $value);

        return $this;
    }

    /** @throws PropertyException */
    public function getOrderBy(): string
    {
        return $this->getProperty(self::PARAMETER_ORDER_BY)->getValue();
    }

    /** @throws TypeException | PropertyException */
    public function setOrderBy(mixed $value): self
    {
        $this->setProperty(self::PARAMETER_ORDER_BY, $value);

        return $this;
    }

    public function getCopy(): self
    {
        return GeneralUtility::makeInstance(get_class($this), $this->dataMap->getClassName(), $this->getParameterArray());
    }

    /** @throws TypeException | PropertyException | ValueException */
    public function __call($name, $arguments)
    {
        if (preg_match('/((?:s|g)et|is|has)([A-Z].*)/', $name, $matches)) {
            $action = $matches[1];
            $propertyName = lcfirst($matches[2]);

            if ($action === 'toggle') {
                return $this->toggleProperty($propertyName, ...$arguments);
            }

            if ($action === 'set') {
                return $this->setProperty($propertyName, ...$arguments);
            }

            if ($action === 'get') {
                return $this->hasProperty($propertyName) ? $this->getProperty($propertyName)->getValue() : null;
            }

            if ($action === 'is') {
                return $this->hasProperty($propertyName) && !empty($this->getProperty($propertyName)->getValue());
            }

            if ($action === 'has') {
                return $this->hasProperty($propertyName);
            }
        }

        throw new ValueException(sprintf('Method "%s" not found in %s', $name, __CLASS__), 1676061375);
    }
}
