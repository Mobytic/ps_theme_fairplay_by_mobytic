<?php

class SitemapController extends SitemapControllerCore
{
    public function getCategoriesLinks()
    {
        $idLang = (int) $this->context->language->id;
        $rootCategory = Category::getRootCategory();

        $rootNode = [
            'id'    => 'category-' . (int) $rootCategory->id,
            'label' => $this->trans('Categories', [], 'Shop.Theme.Global'),
            'url'   => $this->context->link->getCategoryLink($rootCategory->id),
            'children' => $this->buildCategoryLinks((int) $rootCategory->id, $idLang),
        ];


        return [$rootNode];
    }

    /**
     * Build tree of categories recursively in the format used by the sitemap templates.
     */
    protected function buildCategoryLinks($idParent, $idLang)
    {
        $links = [];

        // Get children of this category
        $children = Category::getChildren($idParent, $idLang, true);

        foreach ($children as $child) {
            $node = [
                'id'    => 'category-' . (int) $child['id_category'],
                'label' => $child['name'],
                'url'   => $this->context->link->getCategoryLink(
                    (int) $child['id_category'],
                    $child['link_rewrite']
                ),
            ];

            // Recursively build subcategories
            $childChildren = $this->buildCategoryLinks((int) $child['id_category'], $idLang);
            if (!empty($childChildren)) {
                $node['children'] = $childChildren;
            }

            $links[] = $node;
        }

        return $links;
    }
}
