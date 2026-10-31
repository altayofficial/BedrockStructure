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
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use function array_map;
use function count;

/**
 * Format version 1 layout: exactly two layers, each a list of int tags.
 */
final class ListBlockLayerCodec implements BlockLayerCodec{

	public function decode(ListTag $layers) : array{
		// yeah, version 1 readers refuse anything that doesn't have exactly two layers
		if(count($layers) !== 2){
			throw new StructureException("Format version 1 structures need 2 block index layers, got " . count($layers));
		}
		$result = [];
		foreach($layers as $i => $layer){
			$indices = $layer instanceof ListTag ? $layer->cast(IntTag::class) : null;
			if($indices === null){
				throw new StructureException("Block index layer $i must be a list of ints in format version 1");
			}
			/** @phpstan-var list<int> $values */
			$values = $indices->getAllValues();
			$result[] = $values;
		}
		/** @phpstan-var non-empty-list<list<int>> $result layer count was checked above */
		return $result;
	}

	public function encode(BlockStorage $storage) : ListTag{
		return new ListTag([self::intList($storage->getBlocks()), self::intList($storage->getLiquids())], NBT::TAG_List);
	}

	/**
	 * @param int[] $values
	 * @phpstan-return ListTag<IntTag>
	 */
	private static function intList(array $values) : ListTag{
		return new ListTag(array_map(fn(int $v) => new IntTag($v), $values), NBT::TAG_Int);
	}
}
