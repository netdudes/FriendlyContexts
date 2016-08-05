<?php
namespace Knp\FriendlyContexts\Guesser;

use Doctrine\ORM\EntityManager;
use Knp\FriendlyContexts\Record\Collection\Bag;
use Symfony\Component\PropertyAccess\PropertyAccess;

class EntityGuesser extends AbstractGuesser implements GuesserInterface
{
    public function __construct(Bag $bag)
    {
        $this->bag = $bag;
    }
    public function supports(array $mapping)
    {
        if (array_key_exists('targetEntity', $mapping)) {
            return true;
        }

        return false;
    }

    public function guess($str, array $mapping, $entityManager)
    {
        if(is_object($str)){
            return $str;
        }

        $str = strlen((string) $str) ? $str : null;
        $entity = $this->transform($str, $mapping);
        if ($entity !== null) {
            return $entity;
        }
        if(null === $str){
            return null;
        }

        return $this->getRecordFromDb($str, $mapping, $entityManager);
    }

    public function transform($str, array $mapping)
    {
        $record = $this->bag->getCollection($mapping['targetEntity'])->search($str);
        if (null !== $record) {
            return $record->getEntity();
        }

        return null;
    }

    private function getRecordFromDb($str, $mapping, $em)
    {
        $collection = $this->bag->getCollection($mapping['targetEntity']);

        $reflection = new \ReflectionClass($collection->getReferencial());
        $entity =  $this->findEntityInDb($em, $reflection, $mapping, $str);
        if (null === $entity) {
            $class = $reflection->getName();
            throw new \Exception("Something went wrong. No entity of class '$class' found for identifier '$str''.");
        }
        $key = $this->findValueSpecificKey($reflection->getProperties(), $str, $entity);

        do {
            $this->bag
                ->getCollection($reflection->getName())
                ->attach($entity, [$key => $str]);
            $reflection = $reflection->getParentClass();
        } while (false !== $reflection);

        return $entity;
    }

    public function fake(array $mapping)
    {
        $collection = $this->bag->getCollection($mapping['targetEntity']);
        if (0 === $collection->count()) {
            throw new \Exception(sprintf('There is no record for "%s"', $mapping['targetEntity']));
        }
        $records = array_values($collection->all());

        return $records[array_rand($records)]->getEntity();
    }

    public function getName()
    {
        return 'entity';
    }

    public function findEntityInDb(EntityManager $em, \ReflectionClass $reflection, $mapping, $value)
    {
        $className = $reflection->getName();
        $entities = $em->getRepository($className)->findAll();
        if (empty($entities)) {
            return null;
        }

        foreach ($entities as $entity) {
            if (method_exists($entity, '__toString') && (string) $entity === $value) {
                $this->entityCache[$className][$value] = $entity;

                return $entity;
            }
        }

        $entityMetaData = $em->getClassMetadata($className);
        $accessor = PropertyAccess::createPropertyAccessor();
        foreach ($entityMetaData->fieldNames as $field) {
            foreach ($entities as $entity) {
                $propertyValue = $accessor->getValue($entity, $field);
                if (null !== $propertyValue && $value === $propertyValue) {
                    $this->entityCache[$className][$value] = $entity;

                    return $entity;
                }
            }
        }
    }

    public function findValueSpecificKey($properties, $str, $entity)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            if ($accessor->getValue($entity, $propertyName) === $str) {
                return $propertyName;
            }
        }
        throw new \Exception("Something went wrong. Property should be found for $str on $entity, but it is not.");
    }
}
