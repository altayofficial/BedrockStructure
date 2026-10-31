<?php

/*
 *
 *      _    _ _
 *     / \  | | |_ __ _ _   _
 *    / _ \ | | __/ _` | | | |
 *   / ___ \| | || (_| | |_| |
 *  /_/   \_\_|\__\__,_|\__, |
 *                       |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Altay Team
 * @link https://github.com/altayofficial
 */


declare(strict_types=1);

namespace altay\bedrockstructure\io\layer;

use altay\bedrockstructure\storage\BlockStorage;
use altay\bedrockstructure\StructureException;
use pocketmine\nbt\tag\ListTag;

/**
 * Turns the block_indices tag into plain index layers and back. Each format version stores them a
 * bit differently, see FormatVersion.
 */
interface BlockLayerCodec{

	/**
	 * Decodes the layers, applying the same layer count and tag type rules the client does. Checking the
	 * layer sizes is up to the caller.
	 *
	 * @return int[][]
	 * @phpstan-return non-empty-list<list<int>>
	 *
	 * @throws StructureException
	 */
	public function decode(ListTag $layers) : array;

	public function encode(BlockStorage $storage) : ListTag;
}
