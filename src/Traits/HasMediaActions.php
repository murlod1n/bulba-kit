<?php

namespace Nktlksvch\BulbaKit\Traits;

use Illuminate\Http\Request;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 */
trait HasMediaActions
{
    /**
     * Handle single image uploads for non-gallery media fields.
     *
     * Clears the existing media collection and adds the new file.
     * Stores alt text as a custom property on the media item.
     * Skips gallery fields (handled by handleGalleryUpload).
     */
    protected function handleMediaUpload($item, Request $request): void
    {
        $galleries = $this->getCrudDefinition()->mediaGalleries();

        foreach ($this->getCrudDefinition()->mediaCollections() as $field => $collection) {
            if (in_array($field, $galleries)) {
                continue;
            }

            if ($request->file($field)) {
                $item->clearMediaCollection($collection);
                $item->addMedia($request->file($field))
                    ->withCustomProperties(['alt' => $request->input($field.'_alt', '')])
                    ->toMediaCollection($collection);
            }
        }
    }

    /**
     * Handle single image removal for non-gallery media fields.
     *
     * Checks for `remove_{field}` request input and clears the collection.
     * Skips gallery fields (handled by handleGalleryRemoval).
     */
    protected function handleMediaRemoval($item, Request $request): void
    {
        $galleries = $this->getCrudDefinition()->mediaGalleries();

        foreach ($this->getCrudDefinition()->mediaCollections() as $field => $collection) {
            if (in_array($field, $galleries)) {
                continue;
            }

            if ($request->input('remove_'.$field)) {
                $item->clearMediaCollection($collection);
            }
        }
    }

    /**
     * Update alt text on existing media items.
     *
     * Expects `{collection_name}_alt` in request input.
     */
    protected function updateMediaAlt($item, Request $request): void
    {
        foreach ($item->getMedia() as $media) {
            $altKey = $media->collection_name.'_alt';

            if ($request->has($altKey)) {
                $media->setCustomProperty('alt', $request->input($altKey));
                $media->save();
            }
        }
    }

    /**
     * Handle multiple file uploads for gallery fields.
     *
     * Appends new files to the collection without clearing existing ones.
     * Expects `{field}` to contain an array of UploadedFile instances.
     */
    protected function handleGalleryUpload($item, Request $request): void
    {
        foreach ($this->getCrudDefinition()->mediaGalleries() as $field) {
            $collection = $this->getCrudDefinition()->mediaCollections()[$field] ?? $field;
            $files = $request->file($field, []);

            foreach ($files as $file) {
                $item->addMedia($file)->toMediaCollection($collection);
            }
        }
    }

    /**
     * Remove specific media items from a gallery by ID.
     *
     * Expects `remove_{field}` to contain an array of media IDs to delete.
     */
    protected function handleGalleryRemoval($item, Request $request): void
    {
        foreach ($this->getCrudDefinition()->mediaGalleries() as $field) {
            $idsToRemove = $request->input('remove_'.$field, []);

            foreach ($idsToRemove as $mediaId) {
                $media = $item->getMedia()->firstWhere('id', $mediaId);
                if ($media) {
                    $media->delete();
                }
            }
        }
    }

    /**
     * Reorder gallery items by setting the order property on each media item.
     *
     * Expects `reorder_{field}` to contain an array of media IDs in the desired order.
     */
    protected function handleGalleryReorder($item, Request $request): void
    {
        foreach ($this->getCrudDefinition()->mediaGalleries() as $field) {
            $orderedIds = $request->input('reorder_'.$field, []);

            if (empty($orderedIds)) {
                continue;
            }

            foreach ($orderedIds as $order => $mediaId) {
                $media = $item->getMedia()->firstWhere('id', $mediaId);
                if ($media) {
                    $media->setOrder($order);
                }
            }
        }
    }

    /**
     * Update alt text on specific gallery items by media ID.
     *
     * Expects `alt_{field}` to contain a map of mediaId => altText.
     */
    protected function handleGalleryAlt($item, Request $request): void
    {
        foreach ($this->getCrudDefinition()->mediaGalleries() as $field) {
            $altValues = $request->input('alt_'.$field, []);

            foreach ($altValues as $mediaId => $alt) {
                $media = $item->getMedia()->firstWhere('id', $mediaId);
                if ($media) {
                    $media->setCustomProperty('alt', $alt);
                    $media->save();
                }
            }
        }
    }
}
