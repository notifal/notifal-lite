<?php

namespace Notifal\Domain\Users\DTO;

defined('ABSPATH') || exit;

/**
 * Class UserDTO
 *
 * Represents a user object with only the data needed for tag resolution.
 *
 * @package Notifal\Domain\Users\DTO
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class UserDTO
{
    /**
     * @var int User ID
     */
    private int $id;

    /**
     * @var string First name
     */
    private string $firstName;

    /**
     * @var string Last name
     */
    private string $lastName;

    /**
     * @var string Email address
     */
    private string $email;

    /**
     * @var string Username (user_login)
     */
    private string $username;

    /**
     * @var string User URL
     */
    private string $url;

    /**
     * @var string User nicename
     */
    private string $nicename;

    /**
     * @var string User registration date
     */
    private string $registered;

    /**
     * @var string Display name
     */
    private string $displayName;

    /**
     * UserDTO constructor.
     *
     * @param int    $id
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $username
     * @param string $url
     * @param string $nicename
     * @param string $registered
     * @param string $displayName
     * @since 2.0.0
     */
    public function __construct(
        int $id,
        string $firstName,
        string $lastName,
        string $email,
        string $username = '',
        string $url = '',
        string $nicename = '',
        string $registered = '',
        string $displayName = ''
    ) {
        $this->id          = $id;
        $this->firstName   = $firstName;
        $this->lastName    = $lastName;
        $this->email       = $email;
        $this->username    = $username;
        $this->url         = $url;
        $this->nicename    = $nicename;
        $this->registered  = $registered;
        $this->displayName = $displayName;
    }

    /**
     * Get the user ID.
     *
     * @return int
     * @since 2.0.0
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the user's first name.
     *
     * @return string
     * @since 2.0.0
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * Get the user's last name.
     *
     * @return string
     * @since 2.0.0
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * Get the user's email address.
     *
     * @return string
     * @since 2.0.0
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get the user's username.
     *
     * @return string
     * @since 2.0.0
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Get the user's URL.
     *
     * @return string
     * @since 2.0.0
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the user's nicename.
     *
     * @return string
     * @since 2.0.0
     */
    public function getNicename(): string
    {
        return $this->nicename;
    }

    /**
     * Get the user's registration date.
     *
     * @return string
     * @since 2.0.0
     */
    public function getRegistered(): string
    {
        return $this->registered;
    }

    /**
     * Get the user's display name.
     *
     * @return string
     * @since 2.0.0
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Get a custom meta field for this user.
     *
     * @param string $key Meta key.
     * @return mixed|null Meta value or null if not found.
     * @since 2.0.0
     */
    public function getMeta(string $key)
    {
        return get_user_meta($this->id, $key, true);
    }
}
