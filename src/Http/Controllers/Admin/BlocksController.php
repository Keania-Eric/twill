<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Helpers\BlockRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\View\Factory as ViewFactory;

class BlocksController extends Controller
{
    /**
     * Render an HTML preview of a single block.
     * This is used by the full screen content editor.
     *
     * @param Application $app
     * @param ViewFactory $viewFactory
     * @param Request $request
     * @return string
     */
    public function preview(
        Application $app,
        ViewFactory $viewFactory,
        Request $request,
    ) {
        if ($request->has('activeLanguage')) {
            $app->setLocale($request->get('activeLanguage'));
        }

        $data = $request->except('activeLanguage');

        $mapping = config('twill.block_editor.block_views_mappings');
        $previewRenderChilds = config('twill.block_editor.block_preview_render_childs');

        if ($viewFactory->exists(config('twill.block_editor.block_single_layout'))) {
            $viewFactory->inject(
                'content',
                BlockRenderer::fromCmsArray($data)->render($mapping, [], $previewRenderChilds)
            );
            $result = view(config('twill.block_editor.block_single_layout'));
        } else {
            $result = view(
                'twill::errors.block_layout',
                ['view' => config('twill.block_editor.block_single_layout')]
            );
        }

        return html_entity_decode($result->render());
    }
}
