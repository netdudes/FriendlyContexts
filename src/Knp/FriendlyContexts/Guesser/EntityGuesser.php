<?php

namespace Knp\FriendlyContexts\Guesser;

use Knp\FriendlyContexts\Record\Collection\Bag;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityGuesser extends AbstractGuesser implements GuesserInterface
{
    public function __construct(Bag $bag)
    {
        $this->bag = $bag;
    }

    public function supports(array $mapping)
    {
        if (array_key_exists('targetEntity', $mapping)) {

            return $this->bag->getCollection($mapping['targetEntity'])->count() > 0;
        }

        return false;
    }

    public function transform($str, array $mapping, $em)
    {
        $str = strlen((string) $str) ? $str : null;

        $collection = $this->bag->getCollection($mapping['targetEntity']);
        $record = $collection->search($str);
        if (null !== $record) {
            return $record->getEntity();
        }

        $record = $this->getRecordFromDb($collection, $str, $em);
        if($record !== null){
            return $record;
        }
        return null;
    }

    private function getRecordFromDb($collection, $str, $em)
    {
        $entityClass = $collection->getReferencial();

        // TODO: Maybe we could override these bundle?
        $criteria = [];
        $valuesExploded = explode(' ', $collection->getHeaders()[0]);
        $count = 0;
        foreach($valuesExploded as $string){
            if($count === 0){
                $key = strtolower($string);
                $count++;
            } else {
                $key = $key . ucwords($string);
            }
        }

        $criteria[$key] = $str ;

        $entity =  $em->getRepository($entityClass)->findOneBy($criteria);

        $reflection = new \ReflectionClass($entity);
        do {
            $this->
                bag
                ->getCollection($reflection->getName())
                ->attach($entity, [$key => $string]);
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
}
