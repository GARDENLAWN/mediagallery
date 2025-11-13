<?php

namespace GardenLawn\MediaGallery\Api\Data;

interface GridInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    const  string id = 'id';
    const  string name = 'name';
    const  string sortorder = 'sortorder';
    const  string enabled = 'enabled';

    public function getMediaGalleryId(): ?int;
    public function setMediaGalleryId($id);
    public function getName(): ?string;
    public function setName($name);
    public function getSortOrder(): ?int;
    public function setSortOrder($sortorder);
    public function getEnabled(): ?bool;
    public function setEnabled($enabled);
}
