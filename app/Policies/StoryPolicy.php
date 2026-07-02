<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

class StoryPolicy
{
    /**
     * Admin ko sab kuch allowed — baaki policy methods se pehle chalti hai.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null; // baaki methods decide karengi
    }

    public function view(User $user, Story $story): bool
    {
        return $story->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Story $story): bool
    {
        return $story->user_id === $user->id;
    }

    public function delete(User $user, Story $story): bool
    {
        return $story->user_id === $user->id;
    }
}
