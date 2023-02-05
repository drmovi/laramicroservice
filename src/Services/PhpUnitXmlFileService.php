<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Contracts\State;
use Drmovi\MonorepoGenerator\Dtos\PhpunitTestSuiteItem;
use Symfony\Component\DomCrawler\Crawler;

class PhpUnitXmlFileService implements State
{
    private ?string $backup = null;

    public function __construct(private readonly string $path)
    {
    }

    public function backup(): void
    {
        $path = $this->path . DIRECTORY_SEPARATOR . 'phpunit.xml';
        if (file_exists($path)) {
            $this->backup = file_get_contents($path);
        }
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


    public function addTestDirectories(PhpunitTestSuiteItem ...$phpunitTestSuiteItems): void
    {
        $crawler = $this->getContent();
        foreach ($phpunitTestSuiteItems as $phpunitTestSuiteItem) {
            $crawler->filterXPath('//phpunit/testsuites/testsuite[@name="' . $phpunitTestSuiteItem->testSuiteName . '"]')->each(function (Crawler $node) use ($crawler, $phpunitTestSuiteItem) {
                $child = $crawler->getNode(0)->parentNode->createElement('directory', $phpunitTestSuiteItem->path);
                if ($phpunitTestSuiteItem->suffix) {
                    $child->setAttribute('suffix', $phpunitTestSuiteItem->suffix);
                }
                $node->getNode(0)->appendChild($child);
            });
        }
        $this->setContent($crawler);
    }

    public function removeTestDirectories(PhpunitTestSuiteItem ...$phpunitTestSuiteItems): void
    {
        $crawler = $this->getContent();
        foreach ($phpunitTestSuiteItems as $phpunitTestSuiteItem) {
            $crawler->filterXPath('//phpunit/testsuites/testsuite//*')->each(function (Crawler $node) use ($phpunitTestSuiteItem) {
                if ($node->text() === $phpunitTestSuiteItem->path) {
                    $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                }
            });
        }
        $this->setContent($crawler);
    }

}
