<?php

declare(strict_types=1);

namespace Bolt\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Bolt\Content\ContentType;
use Bolt\Enum\Statuses;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManagerAware;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Tightenco\Collect\Support\Collection as LaravelCollection;

/**
 * @ApiResource(
 *     normalizationContext={"groups"={"get_content"}, "enable_max_depth"=true},
 *     denormalizationContext={"groups"={"put"}},
 *     collectionOperations={"get"},
 *     itemOperations={"get",
 *         "put"={
 *             "denormalization_context"={"groups"={"put"}},
 *         }
 *     }
 * )
 * @ApiFilter(SearchFilter::class)
 * @ORM\Entity(repositoryClass="Bolt\Repository\ContentRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Content implements ObjectManagerAware
{
    use ContentLocalizeTrait;
    use ContentMagicTrait;

    public const NUM_ITEMS = 8; // @todo This can't be a const

    /**
     * @var int
     *
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups("get_content")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=191, name="contenttype")
     * @Groups("get_content")
     */
    private $contentType;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="Bolt\Entity\User", fetch="EAGER")
     * @ORM\JoinColumn(nullable=false)
     * @Groups("put")
     */
    private $author;

    /**
     * @var ?string
     *
     * @ORM\Column(type="string", length=191)
     * @Groups("put")
     */
    private $status = null;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $createdAt;

    /**
     * @var ?\DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups("put")
     */
    private $modifiedAt = null;

    /**
     * @var ?\DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"get_content", "put"})
     */
    private $publishedAt = null;

    /**
     * @var ?\DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups("put")
     */
    private $depublishedAt = null;

    /**
     * @var Collection|Field[]
     *
     * @Groups({"put"})
     * @MaxDepth(1)
     * @ORM\OneToMany(
     *     targetEntity="Bolt\Entity\Field",
     *     mappedBy="content",
     *     indexBy="name",
     *     fetch="EAGER",
     *     orphanRemoval=true,
     *     cascade={"persist"}
     * )
     * @ORM\OrderBy({"sortorder": "ASC"})
     */
    private $fields;

    /** @var ?ContentType */
    private $contentTypeDefinition;

    /**
     * @var Collection|Taxonomy[]
     * @Groups({"get_content", "put"})
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity="Bolt\Entity\Taxonomy", mappedBy="content", cascade={"persist"})
     */
    private $taxonomies;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->taxonomies = new ArrayCollection();
        $this->fields = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @see: Bolt\EventListener\ContentListener
     */
    public function setDefinitionFromContentTypesConfig(LaravelCollection $contentTypesConfig): void
    {
        $this->contentTypeDefinition = ContentType::factory($this->contentType, $contentTypesConfig);
    }

    public function getDefinition(): ?ContentType
    {
        return $this->contentTypeDefinition;
    }

    public function getSlug(): string
    {
        return $this->getField('slug')->__toString();
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    /**
     * @deprecated Backward-compatible alias for `getAuthor`
     */
    public function geUser(): User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): void
    {
        $this->author = $author;
    }

    public function getStatus(): ?string
    {
        if (Statuses::isValid($this->status) === false) {
            $this->status = $this->getDefinition()->get('default_status');
        }

        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (Statuses::isValid($status)) {
            $this->status = $status;
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getModifiedAt(): ?\DateTimeInterface
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(?\DateTimeInterface $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getDepublishedAt(): ?\DateTimeInterface
    {
        return $this->depublishedAt;
    }

    public function setDepublishedAt(?\DateTimeInterface $depublishedAt): self
    {
        $this->depublishedAt = $depublishedAt;

        return $this;
    }

    /**
     * @return Collection|Field[]
     */
    public function getFields(): Collection
    {
        return $this->fields;
    }

    /**
     * @Groups("get_content")
     */
    public function getFieldValues(): array
    {
        return $this->fields
            ->map(function (Field $field) {
                return $field->getFlattenedValue();
            })
            ->toArray();
    }

    public function getFieldValue(string $fieldName): ?array
    {
        if ($this->hasField($fieldName) === false) {
            return null;
        }

        return $this->getField($fieldName)->getValue();
    }

    public function getField(string $fieldName): Field
    {
        if ($this->hasField($fieldName) === false) {
            throw new \InvalidArgumentException(sprintf("Content does not have '%s' field!", $fieldName));
        }

        return $this->fields[$fieldName];
    }

    public function hasField(string $fieldName): bool
    {
        return isset($this->fields[$fieldName]);
    }

    public function addField(Field $field): self
    {
        if ($this->hasField($field->getName())) {
            throw new \InvalidArgumentException(sprintf("Content already has '%s' field!", $field->getName()));
        }

        $this->fields[$field->getName()] = $field;
        $field->setContent($this);

        return $this;
    }

    public function removeField(Field $field): self
    {
        unset($this->fields[$field->getName()]);

        // set the owning side to null (unless already changed)
        if ($field->getContent() === $this) {
            $field->setContent(null);
        }

        return $this;
    }

    /**
     * @Groups("get_content")
     */
    public function getAuthorName(): string
    {
        return $this->getAuthor()->getDisplayName();
    }

    public function getStatuses(): array
    {
        return Statuses::all();
    }

    public function getStatusOptions(): array
    {
        $options = [];

        foreach (Statuses::all() as $option) {
            $options[] = [
                'key' => $option,
                'value' => ucwords($option),
                'selected' => $option === $this->getStatus(),
            ];
        }

        return $options;
    }

    /**
     * @return Collection|Taxonomy[]
     */
    public function getTaxonomies(?string $type = null): Collection
    {
        if ($type) {
            return $this->taxonomies->filter(
                function (Taxonomy $taxonomy) use ($type) {
                    return $taxonomy->getType() === $type;
                }
            );
        }

        return $this->taxonomies;
    }

    public function addTaxonomy(Taxonomy $taxonomy): self
    {
        if ($this->taxonomies->contains($taxonomy) === false) {
            $this->taxonomies[] = $taxonomy;
            $taxonomy->addContent($this);
        }

        return $this;
    }

    public function removeTaxonomy(Taxonomy $taxonomy): self
    {
        if ($this->taxonomies->contains($taxonomy)) {
            $this->taxonomies->removeElement($taxonomy);
            $taxonomy->removeContent($this);
        }

        return $this;
    }

    public function related(): array
    {
        // @todo See Github issue https://github.com/bolt/four/issues/163
        return [];
    }
}
