<?php

namespace App\Forum\Widgets;

use App\Classes\Reactions;
use App\Classes\Template;

/**
 * Renders a combined reaction count with a hover/click picker.
 */
class ReactionWidget
{
    public $Template = '';

    /**
     * @param string      $type         The target type: thread or post.
     * @param int         $id           The target ID.
     * @param int         $page         The thread page containing the target.
     * @param int         $count        The total cached reaction count.
     * @param array       $counts       Reaction counts keyed by reaction type.
     * @param string|null $userReaction The current user's reaction.
     * @param bool        $canReact     Whether the current user can react.
     */
    public function __construct(string $type, int $id, int $page, int $count, array $counts, ?string $userReaction, bool $canReact)
    {
        if (!$canReact)
        {
            if ($count <= 0)
                return;

            $breakdown = $this->BuildBreakdown($counts);

            $static = new Template('reaction', 'static');
            $static->AddEscapedEntry('{count}', $count);
            $static->AddEscapedEntry('{breakdown}', $breakdown);
            $static->Replace();
            $this->Template = $static->template;

            return;
        }

        $options = '';

        foreach (Reactions::ALL as $reaction => $meta)
        {
            $reactionCount = (int) ($counts[$reaction] ?? 0);
            $active = $userReaction === $reaction;

            $option = new Template('reaction', 'option');
            $option->AddEscapedEntry('{reaction}', $reaction);
            $option->AddEscapedEntry('{icon}', $meta['icon']);
            $option->AddEscapedEntry('{label}', $meta['label']);
            $option->AddEscapedEntry('{count}', $reactionCount > 0 ? $reactionCount : '');
            $option->AddEscapedEntry('{active_class}', $active ? 'is-active' : '');
            $option->AddEscapedEntry('{pressed}', $active ? 'true' : 'false');
            $option->Replace();
            $options .= $option->template;
        }

        $form = new Template('reaction', 'form');
        $form->AddEscapedEntry('{type}', $type);
        $form->AddEscapedEntry('{id}', $id);
        $form->AddEscapedEntry('{page}', $page);
        $form->AddEntry('{options}', $options);
        $form->Replace();

        $triggerReaction = $userReaction !== null && Reactions::IsValid($userReaction)
            ? $userReaction
            : null;
        $triggerIcon = $triggerReaction !== null
            ? Reactions::ALL[$triggerReaction]['icon']
            : 'ti-mood-smile';
        $triggerClass = $triggerReaction !== null
            ? 'reaction-' . $triggerReaction . ' is-active'
            : '';

        $picker = new Template('reaction', 'picker');
        $picker->AddEscapedEntry('{trigger_icon}', $triggerIcon);
        $picker->AddEscapedEntry('{trigger_class}', $triggerClass);
        $picker->AddEscapedEntry('{count}', $count);
        $picker->AddEntry('{form}', $form->template);
        $picker->Replace();
        $this->Template = $picker->template;
    }

    private function BuildBreakdown(array $counts): string
    {
        $parts = [];

        foreach (Reactions::ALL as $reaction => $meta)
        {
            $reactionCount = (int) ($counts[$reaction] ?? 0);
            if ($reactionCount > 0)
                $parts[] = $meta['label'] . ' ' . $reactionCount;
        }

        return count($parts) > 0 ? implode(', ', $parts) : 'No reactions';
    }
}

?>
