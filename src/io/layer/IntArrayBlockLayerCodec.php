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
use pocketmine\nbt\tag\IntArrayTag;
use pocketmine\nbt\tag\ListTag;
use function array_map;
use function array_values;
use function count;
use function max;

/**
 * Format version 2 layout: one or two int array layers, the liquid one only when it's actually used.
 */
final class IntArrayBlockLayerCodec implements BlockLayerCodec{

	public function decode(ListTag $layers) : array{
		$count = count($layers);
		if($count < 1 || $count > 2){
			throw new StructureException("Format version 2 structures need 1 or 2 block index layers, got $count");
		}
		$result = [];
		foreach($layers as $i => $layer){
			if(!$layer instanceof IntArrayTag){
				throw new StructureException("Block index layer $i must be an int array in format version 2");
			}
			$values = $layer->getValue();
			// wait, the nbt reader unpacks int arrays as unsigned, so -1 shows up as 0xffffffff here
			if(count($values) > 0 && max($values) > 0x7fffffff){
				$values = array_map(fn(int $v) : int => $v > 0x7fffffff ? $v - 0x100000000 : $v, $values);
			}
			$result[] = $values;
		}
		/** @phpstan-var non-empty-list<list<int>> $result layer count was checked above */
		return $result;
	}

	public function encode(BlockStorage $storage) : ListTag{
		$layers = [new IntArrayTag(array_values($storage->getBlocks()))];
		// files exported by the game skip the liquid layer when nothing is waterlogged
		if($storage->hasLiquids()){
			$layers[] = new IntArrayTag(array_values($storage->getLiquids()));
		}
		return new ListTag($layers, NBT::TAG_IntArray);
	}
}
