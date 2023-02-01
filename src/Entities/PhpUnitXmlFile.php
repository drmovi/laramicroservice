<?php

namespace Drmovi\PackageGenerator\Entities;

use Drmovi\PackageGenerator\Contracts\State;
use Symfony\Component\DomCrawler\Crawler;

class PhpUnitXmlFile implements State
{
    private ?string $backup;

    public function __construct(private readonly string $path)
    {
    }

    public function backup(): void
    {
        $this->backup = file_get_contents($this->path . DIRECTORY_SEPARATOR . 'phpunit.xml');
    }

    public function rollback(): void
    {
        if ($this->backup) {
            file_put_contents($this->path . DIRECTORY_SEPARATOR . 'phpunit.xml', $this->backup);
        }
    }

    public function getContent(): Crawler
    {
        return new Crawler(file_get_contents($this->path . DIRECTORY_SEPARATOR . 'phpunit.xml'));
    }

    public function setContent(Crawler $content): void
    {
        file_put_contents($this->path . DIRECTORY_SEPARATOR . 'phpunit.xml', $content->getNode(0)->ownerDocument->saveXML());
    }


    public function addTestDirectories(string $packageRelativePath): void
    {
        $crawler = $this->getContent();
        $crawler->filterXPath('//phpunit/testsuites/testsuite[@name="Unit"]')->each(function (Crawler $node) use ($crawler, $packageRelativePath) {
            $child = $crawler->getNode(0)->parentNode->createElement('directory', './' . $packageRelativePath . '/tests/Unit');
            $child->setAttribute('suffix', 'Test.php');
            $node->getNode(0)->appendChild($child);
        });
        $crawler->filterXPath('//phpunit/testsuites/testsuite[@name="Feature"]')->each(function (Crawler $node) use ($crawler, $packageRelativePath) {
            $child = $crawler->getNode(0)->parentNode->createElement('directory', './' . $packageRelativePath . '/tests/Feature');
            $child->setAttribute('suffix', 'Test.php');
            $node->getNode(0)->appendChild($child);
        });
        $this->setContent($crawler);
    }

    public function removeTestDirectories(string $packageRelativePath): void
    {
        $crawler = $this->getContent();
        $crawler->filterXPath('//phpunit/testsuites/testsuite//*')->each(function (Crawler $node) use ($packageRelativePath) {
            if (in_array($node->text(), ['./' . $packageRelativePath . '/tests/Unit', './' . $packageRelativePath . '/tests/Feature'])) {
                $node->getNode(0)->parentNode->removeChild($node->getNode(0));
            };
        });
        $this->setContent($crawler);
    }

}
