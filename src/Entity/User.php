<?php

declare(strict_types=1);

namespace Bolt\Entity;

use Bolt\Common\Json;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="Bolt\Repository\UserRepository")
 */
class User implements UserInterface, \Serializable
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups("get_user")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     * @Groups({"get_content", "get_user"})
     */
    private $displayName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", unique=true, length=191)
     * @Assert\NotBlank()
     * @Assert\Length(min=2, max=50)
     * @Groups("get_user")
     */
    private $username;

    /**
     * @var string
     *
     * @ORM\Column(type="string", unique=true, length=191)
     * @Assert\Email()
     * @Groups("get_user")
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=191)
     */
    private $password;

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     * @Groups("get_user")
     */
    private $roles = [];

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups("get_user")
     */
    private $lastseenAt;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $lastIp;

    /**
     * @ORM\Column(type="string", length=191, nullable=true)
     * @Groups("get_user")
     */
    private $locale;

    /**
     * @ORM\Column(type="string", length=191, nullable=true)
     */
    private $backendTheme;

    /**
     * @ORM\Column(type="boolean", options={"default" : false}, nullable=false)
     */
    private $disabled = false;

    /**
     * @ORM\OneToOne(targetEntity="Bolt\Entity\UserAuthToken", mappedBy="user", cascade={"persist", "remove"})
     */
    private $userAuthToken;

    public function __construct()
    {
    }

    /**
     * @return User
     */
    public static function factory(string $displayName = '', string $username = '', string $email = ''): self
    {
        $user = new self();

        $user->setDisplayName($displayName);
        $user->setUsername($username);
        $user->setEmail($email);

        return $user;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function __toString()
    {
        return $this->getdisplayName();
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $slugify = new Slugify(['separator' => '_']);
        $cleanUsername = $slugify->slugify($username);
        $this->username = $cleanUsername;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function enable(): void
    {
        $this->disabled = false;
    }

    public function disable(): void
    {
        $this->disabled = true;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Returns the roles or permissions granted to the user for security.
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // guarantees that a user always has at least one role for security
        if (empty($roles)) {
            $roles[] = 'ROLE_USER';
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * {@inheritdoc}
     */
    public function getSalt(): ?string
    {
        // See "Do you need to use a Salt?" at https://symfony.com/doc/current/cookbook/security/entity_provider.html
        // we're using bcrypt in security.yml to encode the password, so
        // the salt value is built-in and you don't have to generate one

        return null;
    }

    /**
     * Removes sensitive data from the user.
     *
     * {@inheritdoc}
     */
    public function eraseCredentials(): void
    {
        // if you had a plainPassword property, you'd nullify it here
        // $this->plainPassword = null;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return serialize([$this->id, $this->username, $this->password]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        // add $this->salt too if you don't use Bcrypt or Argon2i
        [$this->id, $this->username, $this->password] = unserialize($serialized, ['allowed_classes' => false]);
    }

    public function getLastseenAt(): ?\DateTimeInterface
    {
        return $this->lastseenAt;
    }

    public function setLastseenAt(\DateTimeInterface $lastseenAt): self
    {
        $this->lastseenAt = $lastseenAt;

        return $this;
    }

    public function getLastIp(): ?string
    {
        return $this->lastIp;
    }

    public function setLastIp(?string $lastIp): self
    {
        $this->lastIp = $lastIp;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = Json::findScalar($locale);

        return $this;
    }

    public function getBackendTheme(): ?string
    {
        return $this->backendTheme;
    }

    public function setBackendTheme(?string $backendTheme): self
    {
        $this->backendTheme = $backendTheme;

        return $this;
    }

    public function getUserAuthToken(): ?UserAuthToken
    {
        return $this->userAuthToken;
    }

    public function setUserAuthToken(?UserAuthToken $userAuthToken): self
    {
        $this->userAuthToken = $userAuthToken;

        // set (or unset) the owning side of the relation if necessary
        $newUser = $userAuthToken === null ? null : $this;
        if ($userAuthToken->getUser() !== $newUser) {
            $userAuthToken->setUser($newUser);
        }

        return $this;
    }
}
