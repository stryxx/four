<?php

declare(strict_types=1);

namespace Bolt\DataFixtures;

use Bolt\Common\Str;
use Bolt\Entity\User;
use Bolt\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixtures extends BaseFixture implements FixtureGroupInterface
{
    /** @var UserPasswordEncoderInterface */
    private $passwordEncoder;

    /** @var UserRepository */
    private $users;

    private $append = false;

    /** @var array */
    private $allUsers = [];

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, UserRepository $users)
    {
        $this->passwordEncoder = $passwordEncoder;

        // If ran with `--append` we append users, and use random passwords for them
        if ($this->getOption('--append')) {
            $this->append = true;
        }
        $this->users = $users;
    }

    public function load(ObjectManager $manager): void
    {
        $this->getCurrentUsers();
        $this->loadUsers($manager);

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['with-images', 'without-images'];
    }

    private function loadUsers(ObjectManager $manager): void
    {
        foreach ($this->getUserData() as $userData) {
            /** @var User $allUser */
            foreach ($this->allUsers as $allUser) {
                if (($allUser->getUsername() === $userData['username']) || ($allUser->getEmail() === $userData['username'])) {
                    continue 2;
                }
            }

            $user = new User();
            $user->setDisplayName($userData['displayname']);
            $user->setUsername($userData['username']);
            $user->setPassword($this->passwordEncoder->encodePassword($user, $userData['password']));
            $user->setEmail($userData['email']);
            $user->setRoles($userData['roles']);
            $user->setLocale('en');
            $user->setBackendTheme('default');

            $manager->persist($user);
            $this->addReference('user_' . $userData['username'], $user);
        }

        $manager->flush();
    }

    private function getCurrentUsers(): void
    {
        $this->allUsers = $this->users->findBy([], ['username' => 'ASC'], 100);

        /** @var User $user */
        foreach ($this->allUsers as $user) {
            $this->addReference('user_' . $user->getUsername(), $user);
        }
    }

    private function getUserData(): array
    {
        return [
            [
                'displayname' => 'Admin',
                'username' => 'admin',
                'password' => $this->append ? Str::generatePassword(10) : 'admin%1',
                'email' => 'admin@example.org',
                'roles' => ['ROLE_ADMIN'],
            ],
            [
                'displayname' => 'Gekke Henkie',
                'username' => 'henkie',
                'password' => $this->append ? Str::generatePassword(10) : 'henkie%1',
                'email' => 'henkie@example.org',
                'roles' => ['ROLE_EDITOR'],
            ],
            [
                'displayname' => 'Jane Doe',
                'username' => 'jane_admin',
                'password' => $this->append ? Str::generatePassword(10) : 'jane%1',
                'email' => 'jane_admin@example.org',
                'roles' => ['ROLE_ADMIN'],
            ],
            [
                'displayname' => 'Tom Doe',
                'username' => 'tom_admin',
                'password' => $this->append ? Str::generatePassword(10) : 'tom%1',
                'email' => 'tom_admin@example.org',
                'roles' => ['ROLE_ADMIN'],
            ],
            [
                'displayname' => 'John Doe',
                'username' => 'john_user',
                'password' => $this->append ? Str::generatePassword(10) : 'john%1',
                'email' => 'john_user@example.org',
                'roles' => ['ROLE_USER'],
            ],
        ];
    }
}
