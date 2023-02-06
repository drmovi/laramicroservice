<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Contracts\State;
use Drmovi\MonorepoGenerator\Dtos\PhpunitTestSuiteItem;
use Symfony\Component\DomCrawler\Crawler;

class PhpUnitXmlFileService implements State
{
    private ?string $backup = null;

    public function __construct(private string $path)
    {
        $this->path = $this->path . DIRECTORY_SEPARATOR . 'phpunit.xml';
    }

    public function backup(): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $this->backup = file_get_contents($this->path);
    }

    public function rollback(): void
    {
        if (!$this->canOperate()) {
            return;
        }
        file_put_contents($this->path, $this->backup);

    }

    public function getContent(): ?Crawler
    {
        if (!$this->canOperate()) {
            return null;
        }
        return new Crawler(file_get_contents($this->path));
    }

    public function setContent(Crawler $content): void
    {
        if (!$this->canOperate()) {
            return;
        }
        file_put_contents($this->path, $content->getNode(0)->ownerDocument->saveXML());
    }


    public function addTestDirectories(PhpunitTestSuiteItem ...$phpunitTestSuiteItems): void
    {
        if (!$this->canOperate()) {
            return;
        }
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
        if (!$this->canOperate()) {
            return;
        }
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

    private function canOperate(): bool
    {
        return file_exists($this->path);
    }

}
