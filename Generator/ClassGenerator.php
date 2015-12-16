<?php

namespace Outspaced\PowerGeneratorBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Sensio\Bundle\GeneratorBundle\Generator;

class ClassGenerator extends Generator\Generator
{
    /**
     * @param BundleInterface $bundle
     * @param string $section
     * @param string $class
     * @param array  $fields
     * @throws \RuntimeException
     */
    public function generate(BundleInterface $bundle, $section, $class, array $fields)
    {
        $dir = $bundle->getPath();

        $classFile    = $dir.'/'.$section.'/'.$class.'.php';
        $unitTestFile = $dir .'/Tests/'.$section.'/'.$class.'Test.php';

        if (file_exists($classFile)) {
            throw new \RuntimeException(sprintf('Class "%s:%s" already exists', $section, $class));
        }

        $useStatements = [];

        foreach ($fields as $key => $field) {

            $fields[$key]['fullyQualifiedType'] = $fields[$key]['type'];

            // Lower and upper case!
            $fields[$key]['fieldName'] = lcfirst($field['fieldName']);
            $fields[$key]['fieldNameCapitalized'] = ucfirst($field['fieldName']);

            // Namespace!
            if (preg_match('/(?<lower>.+)\\\\(?<top>.+)\\\\(?<class>.+)/i', $field['type'], $matches)) {
                $fields[$key]['type'] = $matches['top'] . '\\' . $matches['class'];
                $useStatements[] = $matches['lower'] . '\\' . $matches['top'];
            }

            // Type hint!
            if ($this->isTypeHintable($fields[$key]['type'])) {
                $fields[$key]['typeHint'] = $fields[$key]['type'];
            } else {
                $fields[$key]['typeHint'] = '';
            }

            $fields[$key]['testValue'] = $this->getTestValue($fields[$key]['fullyQualifiedType']);
        }

        $parameters = [
            'namespace' => $bundle->getNamespace(),
            'bundle'    => $bundle->getName(),
            'section'   => $section,
            'class'     => $class,
            'fields'    => $fields,
            'uses'      => $useStatements
        ];

        $this->renderFile('class/UnitTest.php.twig', $unitTestFile, $parameters);
        $this->renderFile('class/Class.php.twig', $classFile, $parameters);
    }

    /**
     * @param  string $type
     * @return boolean
     */
    protected function isTypeHintable($type)
    {
        $nonTypeHintable = [
            '', 'string', 'int', 'mixed', 'number', 'void', 'object', 'real', 'double', 'float', 'resource', 'null', 'bool', 'boolean'
        ];

        return !in_array($type, $nonTypeHintable);
    }

    /**
     * @param  string $type
     * @return string
     */
    protected function getTestValue($type)
    {
        switch ($type) {
            case '':
            case 'mixed':
            case 'void':
            case 'object':
            case 'real':
            case 'resource':
            case 'null':
                return '';
            case  'string':
               return '"I am a string"';
            case 'int':
            case 'number':
                return 42;
            case 'double':
            case 'float':
                return 9.95;
            case 'bool':
            case 'boolean':
                return 'true';
            default:
                return "\$this
            ->getMockBuilder('{$type}')
            ->disableOriginalConstructor()
            ->getMock()";
        }
    }
}
