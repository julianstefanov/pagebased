<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\Domain\Model;

use DateTime;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use Zeroseven\Rampage\Domain\Model\Entity\ParentPage;

abstract class AbstractPage extends AbstractEntity
{
    public const TABLE_NAME = 'pages';

    protected int $documentType;
    protected int $l10nParent;
    protected string $title;
    protected string $subtitle;
    protected string $navigationTitle;
    protected string $description;
    protected string $abstract;
    protected ?ParentPage $parentPage = null;
    protected DateTime $lastChange;
    protected ?FileReference $firstMedia = null;
    protected ?FileReference $firstImage = null;
    protected ?ObjectStorage $media = null;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Lazy
     */
    protected ObjectStorage $fileReferences;


    public function __construct()
    {
        $this->initStorageObjects();
    }

    protected function initStorageObjects(): void
    {
        $this->fileReferences = new ObjectStorage();
    }

    public function getUid(): int
    {
        if ((int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('language', 'id', 0) > 0) {
            return (int)$this->l10nParent;
        }

        return (int)$this->uid;
    }

    public function getDocumentType(): int
    {
        return (int)$this->documentType;
    }

    public function setDocumentType(int $documentType): self
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getTitle(): string
    {
        return (string)$this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getSubtitle(): string
    {
        return (string)$this->subtitle;
    }

    public function setSubtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function getNavigationTitle(): string
    {
        return (string)$this->navigationTitle;
    }

    public function setNavigationTitle(string $navigationTitle): self
    {
        $this->navigationTitle = $navigationTitle;
        return $this;
    }

    public function getDescription(): string
    {
        return (string)$this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getAbstract(): string
    {
        return (string)$this->abstract;
    }

    public function setAbstract(string $abstract): self
    {
        $this->abstract = $abstract;
        return $this;
    }

    public function getLastChange(): ?DateTime
    {
        return $this->lastChange;
    }

    public function getParentPage(): ?ParentPage
    {
        return $this->parentPage;
    }

    public function setLastChange(DateTime $lastChange): self
    {
        $this->lastChange = $lastChange;
        return $this;
    }

    public function getFileReferences(): ?ObjectStorage
    {
        return $this->fileReferences;
    }

    public function setFileReferences(ObjectStorage $fileReferences): self
    {
        $this->fileReferences = $fileReferences;
        return $this;
    }

    public function getMedia(): ?ObjectStorage
    {
        if ($this->media === null && $fileReferences = $this->getFileReferences()) {
            $this->media = GeneralUtility::makeInstance(ObjectStorage::class);

            foreach ($fileReferences->toArray() as $fileReference) {
                if ($file = $fileReference instanceof \TYPO3\CMS\Extbase\Domain\Model\FileReference ? $fileReference->getOriginalResource() : null) {
                    $this->media->attach($file);
                }
            }
        }

        return $this->media;
    }

    public function setMedia(ObjectStorage $media): self
    {
        $this->media = $media;
        $this->firstMedia = null;
        $this->firstImage = null;
        return $this;
    }

    public function getFirstMedia(): ?FileReference
    {
        if ($this->firstMedia === null && ($media = $this->getMedia()) && $media->offsetExists(0)) {
            return $this->firstMedia = $media->offsetGet(0);
        }

        return $this->firstMedia;
    }

    public function getFirstImage(): ?FileReference
    {
        if ($this->firstImage === null && $media = $this->getMedia()) {
            foreach ($media->toArray() ?? [] as $asset) {
                if ($asset->getType() === AbstractFile::FILETYPE_IMAGE) {
                    return $this->firstImage = $asset;
                }
            }
        }

        return $this->firstImage;
    }
}
