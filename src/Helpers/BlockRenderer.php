<?php

namespace A17\Twill\Helpers;

use A17\Twill\Models\Behaviors\HasBlocks;
use A17\Twill\Models\Block as A17Block;
use A17\Twill\Models\Model;
use A17\Twill\Services\Blocks\Block;
use A17\Twill\Services\Blocks\RenderData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * This class can take either an Editor or a Cms array of data to render.
 *
 * Based on the input it will build a rendering array (nested) based on the block classes.
 *
 * A block class list may look like this:
 *
 * ```
 * TwoColumnsBlock
 *  ->children: [
 *      TextBlock: [
 *          ActionBlock
 *      ]
 *      ImageBlock: [
 *      ]
 *  ]
 * ```
 */
class BlockRenderer
{
    /**
     * A list of root blocks.
     *
     * @var \A17\Twill\Services\Blocks\Block[]
     */
    public array $rootBlocks = [];

    public bool $inEditor = false;

    public function __construct(array $blocks = [], bool $inEditor = false)
    {
        $this->rootBlocks = $blocks;
        $this->inEditor = $inEditor;
    }

    public function render(
        array $blockViewMappings = [],
        array $data = [],
        bool $renderChildren = false,
    ): string {
        $viewResult = [];
        /** @var Block $block */
        foreach ($this->rootBlocks as $block) {
            $viewResult[] = $block->renderView($blockViewMappings, $data, $renderChildren, $this->inEditor);
        }

        return join('', $viewResult);
    }

    public static function fromCmsArray(array $data): self
    {
        return new self(
            [self::getNestedBlocksForData($data, $data['editor_name'])],
            true
        );
    }

    private static function getNestedBlocksForData(
        array $data,
        string $editorName,
        string $parentEditorName = null
    ): Block {
        $type = Str::replace('a17-block-', '', $data['type']);
        $class = Block::getForType($type);

        $children = [];

        foreach ($data['blocks'] as $editor => $childBlocks) {
            foreach ($childBlocks as $childBlock) {
                $children[] = self::getNestedBlocksForData(
                    $childBlock,
                    editorName: $editorName,
                    parentEditorName: $editor
                );
            }
        }

        $class->setRenderData(new RenderData(
            block: A17Block::make()->fill([
                'type' => $type,
                'content' => $data['content'],
                'editor_name' => $editorName,
            ]),
            editorName: $editorName,
            children: $children,
            parentEditorName: $parentEditorName,
            inEditor: true,
        ));

        return $class;
    }

    public static function fromEditor(
        Model $model,
        string $editorName,
    ): self {
        if (! isset(class_uses_recursive($model)[HasBlocks::class])) {
            throw new \Exception('Model ' . $model::class . ' does not implement HasBlocks');
        }

        $renderer = new self();

        // Get the blocks.
        // @todo: This is a bit more strict than the old implementation. I guess that is fine
        // as we do this in 3.x.
        /** @var A17\Twill\Models\Block[] $blocks */
        $blocks = $model->blocks()->whereEditorName($editorName)->whereParentId(null)->get();

        foreach ($blocks as $block) {
            $data = self::getNestedBlocksForBlock($block, $model, $editorName);
            $renderer->rootBlocks[] = $data;
        }

        return $renderer;
    }

    private static function getNestedBlocksForBlock(
        A17Block $block,
        Model $rootModel,
        string $editorName
    ): Block {
        $class = Block::getForType($block->type);

        /** @var A17\Twill\Models\Block[] $childBlocks */
        $childBlocks = A17Block::whereParentId($block->id)->get();

        $children = [];

        foreach ($childBlocks as $childBlock) {
            $children[] = self::getNestedBlocksForBlock(
                block: $childBlock,
                rootModel: $rootModel,
                editorName: $editorName,
            );
        }

        $class->setRenderData(new RenderData(
            block: $block,
            model: $rootModel,
            editorName: $editorName,
            children: $children,
            parentEditorName: $block->child_key
        ));

        return $class;
    }
}
