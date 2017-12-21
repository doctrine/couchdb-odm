<?php

namespace Doctrine\ODM\CouchDB\Tools\Console\Command;

use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console,
    Doctrine\ODM\CouchDB\Tools\Console\MetadataFilter;

/**
 * Command to (re)generate the proxy classes used by doctrine.
 *
 * @since   1.0
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class GenerateProxiesCommand extends Console\Command\Command
{
    /**
     * @see Console\Command\Command
     */
    protected function configure()
    {
        $this->setName('couchdb:odm:generate-proxies')
             ->setDescription('Generates proxy classes for document classes.')
             ->setDefinition(array(
                 new InputOption(
                     'filter', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                     'A string pattern used to match documents that should be processed.'
                 ),
                 new InputArgument(
                     'dest-path', InputArgument::OPTIONAL,
                     'The path to generate your proxy classes. If none is provided, it will attempt to grab from configuration.'
                 ),
             ));
    }

    /**
     * @see Console\Command\Command
     */
    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $dm = $this->getHelper('couchdb')->getDocumentManager();
        
        $metadatas = $dm->getMetadataFactory()->getAllMetadata();
        $metadatas = MetadataFilter::filter($metadatas, $input->getOption('filter'));

        // Process destination directory
        if (($destPath = $input->getArgument('dest-path')) === null) {
            $destPath = $dm->getConfiguration()->getProxyDir();
        }

        if ( ! is_dir($destPath)) {
            mkdir($destPath, 0777, true);
        }

        $destPath = realpath($destPath);

        if ( ! file_exists($destPath)) {
            throw new \InvalidArgumentException(
                sprintf("Proxies destination directory '<info>%s</info>' does not exist.", $destPath)
            );
        } elseif ( ! is_writable($destPath)) {
            throw new \InvalidArgumentException(
                sprintf("Proxies destination directory '<info>%s</info>' does not have write permissions.", $destPath)
            );
        }

        if ( ! count($metadatas)) {
            $output->write('No Metadata Classes to process.' . PHP_EOL);
            return;
        }
        
        foreach ($metadatas as $metadata) {
            $output->write(sprintf('Processing document "<info>%s</info>"', $metadata->name) . PHP_EOL);
        }

        // Acutally generate the Proxies
        $dm->getProxyFactory()->generateProxyClasses($metadatas, $destPath);

        $output->write(PHP_EOL . sprintf('Proxy classes generated to "<info>%s</INFO>"', $destPath) . PHP_EOL);
    }
}
