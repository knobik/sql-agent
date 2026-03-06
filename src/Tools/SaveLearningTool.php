<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Models\Learning;
use Prism\Prism\Tool;
use RuntimeException;

class SaveLearningTool extends Tool
{
    public function __construct()
    {
        $categories = array_map(
            fn (LearningCategory $cat) => $cat->value,
            LearningCategory::cases()
        );

        $this
            ->as('save_learning')
            ->for('Save a discovery to the knowledge base (type errors, date formats, column quirks, business logic).')
            ->withStringParameter('title', 'A short, descriptive title for the learning (max 100 characters).')
            ->withStringParameter('description', 'A detailed description of what was learned and why it matters.')
            ->withEnumParameter('category', 'The category of this learning.', $categories)
            ->withStringParameter('sql', 'Optional: The SQL query related to this learning.', required: false)
            ->using($this);
    }

    public function __invoke(string $title, string $description, string $category, ?string $sql = null): string
    {
        if (! config('sql-agent.learning.enabled')) {
            throw new RuntimeException('Learning feature is disabled.');
        }

        $title = trim($title);
        $description = trim($description);
        $sql = $sql !== null ? trim($sql) : null;

        if (empty($title)) {
            throw new RuntimeException('Title is required.');
        }

        if (strlen($title) > 100) {
            throw new RuntimeException('Title must be 100 characters or less.');
        }

        if (empty($description)) {
            throw new RuntimeException('Description is required.');
        }

        $categoryEnum = LearningCategory::tryFrom($category);
        if ($categoryEnum === null) {
            $validCategories = implode(', ', array_map(
                fn (LearningCategory $cat) => $cat->value,
                LearningCategory::cases()
            ));
            throw new RuntimeException("Invalid category. Valid categories are: {$validCategories}");
        }

        $learning = Learning::create([
            'title' => $title,
            'description' => $description,
            'category' => $categoryEnum,
            'sql' => $sql ?: null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Learning saved successfully.',
            'learning_id' => $learning->id,
            'title' => $learning->title,
            'category' => $learning->category->value,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
