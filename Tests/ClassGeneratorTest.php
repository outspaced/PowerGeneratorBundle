<?php

namespace Outspaced\PowerGeneratorBundle\Tests;

use Sensio\Bundle\GeneratorBundle\Tests\Generator as SensioGenerator;
use Outspaced\PowerGeneratorBundle\Generator;

class ClassGeneratorTest extends SensioGenerator\GeneratorTest
{
    public function testGenerateClass()
    {
        $this->getGenerator()
            ->generate($this->getBundle(), 'Section', 'Class', [['fieldName' => 'Foo', 'type' => 'FooType']]);

        $files = array(
            'Section/Class.php',
            'Tests/Section/ClassTest.php',
        );

        foreach ($files as $file) {
            $this->assertTrue(file_exists($this->tmpDir.'/'.$file), sprintf('%s has been generated', $file));
        }

        $content = file_get_contents($this->tmpDir.'/Section/Class.php');
        $strings = array(
            'namespace Foo\\BarBundle\\Section',
            'class Class',
        );

        foreach ($strings as $string) {
            $this->assertContains($string, $content);
        }

        $content = file_get_contents($this->tmpDir.'/Tests/Section/ClassTest.php');
        $strings = array(
            'namespace Foo\\BarBundle\\Tests\\Section',
            'class ClassTest',
        );

        foreach ($strings as $string) {
            $this->assertContains($string, $content);
        }
    }

    public function testGenerateActions()
    {
        $generator = $this->getGenerator();
        $fields = [
            0 => [
                'fieldName' => 'FooField',
                'type' => 'Foo\Bar\Baz'
            ],
            1 => [
                'fieldName' => 'BarField',
                'type' => 'int'
            ],
        ];

        $generator->generate($this->getBundle(), 'Section', 'Class', $fields);

        $content = file_get_contents($this->tmpDir.'/Section/Class.php');
        $strings = array(
            'use Foo\\Bar',
            'public function setFooField(Bar\Baz $fooField)',
            'public function getFooField()',
            'public function setBarField($barField)',
            'public function getBarField()',
//             '@param Bar\\Baz $fooField',
            '@param Bar\\Baz',
        );

        foreach ($strings as $string) {
            $this->assertContains($string, $content);
        }

        $content = file_get_contents($this->tmpDir.'/Tests/Section/ClassTest.php');
        $strings = array(
            'namespace Foo\\BarBundle\\Tests\\Section',
            'use Foo\BarBundle\Section',
            'use Foo\Bar',
            'class ClassTest extends \\PHPUnit_Framework_TestCase',
            'public function testSetFooField()',
        );

        foreach ($strings as $string) {
            $this->assertContains($string, $content);
        }
    }

    protected function getGenerator()
    {
        $generator = new Generator\ClassGenerator();
        $generator->setSkeletonDirs(__DIR__.'/../Resources/skeleton');

        return $generator;
    }

    protected function getBundle()
    {
        $bundle = $this->getMock('Symfony\Component\HttpKernel\Bundle\BundleInterface');
        $bundle->expects($this->any())->method('getPath')->will($this->returnValue($this->tmpDir));
        $bundle->expects($this->any())->method('getName')->will($this->returnValue('FooBarBundle'));
        $bundle->expects($this->any())->method('getNamespace')->will($this->returnValue('Foo\BarBundle'));

        return $bundle;
    }
}
