<?php

namespace Knp\FriendlyContexts\Guesser;

class IntGuesser extends AbstractGuesser implements GuesserInterface
{
    const DEFAULT_MAX = 2000000000;

    public function supports(array $mapping)
    {
        $mapping = array_merge([ 'type' => null ], $mapping);

        return $mapping['type'] === 'integer';
    }

    public function transform($str, array $mapping = null)
    {
        return (int) round($str);
    }

    public function fake(array $mapping)
    {
        $min = 0;
        $max = $this->determineMaxValue($mapping);

        return current($this->fakers)->fake('numberBetween', [$min, $max]);
    }

    /**
     * @param array $mapping
     *
     * @return int
     */
    private function determineMaxValue(array $mapping)
    {
        try {
            $length = $this->getLengthFromMapping($mapping);
            $maxValue = (int)str_repeat('9', $length);
        } catch (\Exception $e) {
            $maxValue = self::DEFAULT_MAX;
        }

        return $maxValue;
    }

    /**
     * @param array $mapping
     *
     * @throws \Exception
     *
     * @return int
     */
    private function getLengthFromMapping(array $mapping)
    {
        if (!isset($mapping['length'])) {
            throw new \Exception('The "length" key is not defined');
        }

        $isLengthValid = $mapping['length'] > 0
            && $mapping['length'] < strlen(self::DEFAULT_MAX);
        if (!$isLengthValid) {
            throw new \Exception('The "length" key value is not valid');
        }

        return $mapping['length'];
    }

    public function getName()
    {
        return 'int';
    }
}
