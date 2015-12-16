<?php

namespace Outspaced\PowerGeneratorBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Bundle;

use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Generator\ControllerGenerator;
use Sensio\Bundle\GeneratorBundle\Command\GeneratorCommand;
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineEntityGenerator;

use Outspaced\PowerGeneratorBundle\Generator;

class GenerateClassCommand extends GeneratorCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('generate:class')
            ->setDescription('Generate a class')
        ;

        $this
            ->addOption('class', '', InputOption::VALUE_REQUIRED, 'The name of the class to create')
            ->addOption('section', null, InputOption::VALUE_REQUIRED, 'The top level of the namespace')
            ->addOption('bundle', null, InputOption::VALUE_REQUIRED, 'The bundle to generate the class inside')
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'The fields to create with the new entity')
        ;
    }

    /**
     *
     * @return Generator\ClassGenerator
     */
    protected function createGenerator()
    {
        $generator = new Generator\ClassGenerator();

        return $generator;
    }

    /**
     * This exists solely to overload the value for __DIR__ so that it uses values from
     *  the PowerGeneratorBundle
     *
     * {@inheritDoc}
     * @see GeneratorCommand::getSkeletonDirs()
     */
    protected function getSkeletonDirs(Bundle\BundleInterface $bundle = null)
    {
        $skeletonDirs = array();

        exit($bundle->getPath().'/Resources/SensioGeneratorBundle/skeleton');

        if (isset($bundle) && is_dir($dir = $bundle->getPath().'/Resources/SensioGeneratorBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        if (is_dir($dir = $this->getContainer()->get('kernel')->getRootdir().'/Resources/SensioGeneratorBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        $skeletonDirs[] = __DIR__.'/../Resources/skeleton';
        $skeletonDirs[] = __DIR__.'/../Resources';

        return $skeletonDirs;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dirs = $this->getSkeletonDirs($input->getOption('bundle'));

        // So I need to generate the class AND the unit test
        $generator = $this->getGenerator();
        $generator->generate(
            $input->getOption('bundle'),
            $input->getOption('section'),
            $input->getOption('class'),
            $input->getOption('fields')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the PowerGenerator class generator');

        // namespace
        $output->writeln(array(
            'First, you need to give the class name you want to generate.',
            'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>',
            '',
        ));

        while (true) {

            $question = new Question(
                $questionHelper->getQuestion('Class name', $input->getOption('class')),
                $input->getOption('class')
            );

            $class = $questionHelper->ask($input, $output, $question);

            list($bundle, $section, $class) = $this->parseShortcutNotation($class);

            try {

                $b = $this->getContainer()
                    ->get('kernel')
                    ->getBundle($bundle);

                if (!file_exists($b->getPath().'/'.$section.'/'.$class.'.php')) {
                    break;
                }

                $output->writeln(sprintf('<bg=red>Class "%s:%s:%s" already exists.</>', $bundle, $section, $class));
            } catch (\InvalidArgumentException $e) {
                // This is incredibly presumptuous
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            } catch (\Exception $e) {
                $output->writeln('<bg=red>Error: '.$e->getMessage().'</>');
                $output->writeln($e->getFile().':'.$e->getLine());
            }
        }

        $fields = $this->addFields($input, $output, $questionHelper, $b);

        $input->setOption('fields', $fields);
        $input->setOption('bundle', $b);
        $input->setOption('section', $section);
        $input->setOption('class', $class);

        $output->writeln('OK now go away');
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @param  QuestionHelper  $questionHelper
     * @throws \InvalidArgumentException
     * @return array
     */
    private function addFields(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper, Bundle\BundleInterface $bundle)
    {
        $fields = [];

        $output->writeln([
            '',
            'Add some fields to the class',
            '',
        ]);

        $lengthValidator = function ($length) {
            if (!$length) {
                return $length;
            }

            $result = filter_var($length, FILTER_VALIDATE_INT, array(
                'options' => array('min_range' => 1),
            ));

            if (false === $result) {
                throw new \InvalidArgumentException(sprintf('Invalid length "%s".', $length));
            }

            return $length;
        };

        $generator = $this->getGenerator($bundle);

        while (true) {
            $output->writeln('');

            // Ask column name
            $question = new Question($questionHelper->getQuestion('New field name (press <return> to stop adding fields)', null), null);
            $question->setValidator(function ($name) use ($fields, $generator) {
                if (isset($fields[$name]) || 'id' == $name) {
                    throw new \InvalidArgumentException(sprintf('Field "%s" is already defined.', $name));
                }

                return $name;
            });

            $fieldName = $questionHelper->ask($input, $output, $question);
            if (!$fieldName) {
                break;
            }

            // Ask type
            $question = new Question($questionHelper->getQuestion('Field type', null), null);
            $type = $questionHelper->ask($input, $output, $question);

            $data = [
                'fieldName'  => $fieldName,
                'type'       => $type
            ];

            $fields[$fieldName] = $data;
        }

        return $fields;
    }

    /**
     * @param  string $shortcut
     * @throws \InvalidArgumentException
     * @return array
     */
    public function parseShortcutNotation($shortcut)
    {
        $parts = explode(':', $shortcut);

        if (count($parts) < 3) {
            throw new \InvalidArgumentException(sprintf('The class name must contain 2 :s ("%s" given, expecting something like AcmeBlogBundle:Services:PostService)', $shortcut));
        }

        return $parts;
    }
}