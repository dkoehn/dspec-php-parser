<?php

namespace DKoehn\DSpec\Watcher;

use Kwf\FileWatcher\Watcher;
use Kwf\FileWatcher\Event\Modify;
use Kwf\FileWatcher\Event\Create;
use Kwf\FileWatcher\Event\Delete;
use Kwf\FileWatcher\Event\Move;
use Kwf\FileWatcher\Event\QueueFull;

use DKoehn\DSpec\Cache\DependencyCache;
use DKoehn\DSpec\Parser\CachedParser;
use Kwf\FileWatcher\Event\AbstractEvent;
use DKoehn\DSpec\Parser\Adt;
use DKoehn\DSpec\TestRunner\TestRunner;
use Symfony\Component\Console\Output\OutputInterface;

class FileWatcher
{
    /** @var DependencyCache */
    protected $cache;

    /** @var CachedParser */
    protected $cachedParser;

    /** @var TestRunner */
    protected $testRunner;
    /** @var OutputInterface */
    protected $output;

    protected $cacheFile;

    public function __construct(
        DependencyCache $cache,
        CachedParser $cachedParser,
        TestRunner $testRunner,
        OutputInterface $output,
        string $cacheFile = null
    ) {
        $this->cache = $cache;
        $this->cachedParser = $cachedParser;
        $this->testRunner = $testRunner;
        $this->output = $output;
        $this->cacheFile = $cacheFile;
    }

    public function onModify(AbstractEvent $e)
    {
        $this->parseFile($e->filename);
    }

    public function onDelete(Delete $e)
    {
        $this->removeFile($e->filename);

        $this->testRunner->runTestsForGitUnstaged();
    }

    public function onMove(Move $e)
    {
        $this->removeFile($e->filename);
        $this->parseFile($e->destFilename);
    }

    protected function removeFile($filePath)
    {
        $this->cache->removeByFQN($filePath);
    }

    protected function parseFile($filePath)
    {
        $this->cachedParser->parse($this->cache, [$filePath]);

        if ($this->cacheFile !== null) {
            file_put_contents($this->cacheFile, serialize($this->cache));
        }

        $this->cache->setDependencyFilePaths();

        $this->testRunner->runTestsForGitUnstaged();
    }

    public function watch($paths)
    {
        $watcher = Watcher::create($paths);
        $watcher->addListener(Modify::NAME, [$this, 'onModify']);
        $watcher->addListener(Create::NAME, [$this, 'onModify']);
        $watcher->addListener(Delete::NAME, [$this, 'onDelete']);
        $watcher->addListener(Move::NAME, [$this, 'onMove']);
        // $watcher->addListener(QueueFull::NAME, function(QueueFull $e) use ($watcher) {
        //     // TODO: Too many changes, should stop and re-process?
        //     // $watcher->stop();
        // });

        $watcher->start();
    }
}
