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

namespace altay\bedrockstructure\rotation;

use altay\bedrockstructure\palette\Palette;
use altay\bedrockstructure\palette\PaletteSet;
use altay\bedrockstructure\storage\BlockStorage;
use altay\bedrockstructure\Structure;

/**
 * Builds rotated copies of structures. Only the active palette and its block entity data come along;
 * entities are dropped since their positions are absolute and we can't sanely rotate those.
 */
final class StructureRotator{

	public function __construct(
		private BlockRotator $blockRotator = new BlockRotator()
	){}

	public function rotate(Structure $structure, Rotation $rotation) : Structure{
		$source = $structure->getPalettes()->getActive();
		$palette = new Palette();
		// rotate every palette entry once instead of every block
		$remap = [BlockStorage::NO_BLOCK => BlockStorage::NO_BLOCK];
		for($i = 0, $count = $source->count(); $i < $count; ++$i){
			$remap[$i] = $source->isUnknown($i) ?
				// hmm, we can't rotate what we can't decode, so it goes over as is
				$palette->add($source->getStateData($i)) :
				$palette->indexOf($this->blockRotator->rotate($source->getBlock($i), $rotation));
		}

		$storage = $structure->getStorage();
		[$sizeX, $sizeY, $sizeZ] = [$storage->getSizeX(), $storage->getSizeY(), $storage->getSizeZ()];
		// the turned structure swaps width and depth
		$rotated = BlockStorage::filled($sizeZ, $sizeY, $sizeX, BlockStorage::NO_BLOCK);
		for($x = 0; $x < $sizeX; ++$x){
			for($z = 0; $z < $sizeZ; ++$z){
				[$newX, $newZ] = $rotation->rotatePosition($x, $z, $sizeX, $sizeZ);
				for($y = 0; $y < $sizeY; ++$y){
					$offset = $storage->offset($x, $y, $z);
					$newOffset = $rotated->offset($newX, $y, $newZ);
					$rotated->setBlock($newOffset, $remap[$storage->getBlock($offset)]);
					$rotated->setLiquid($newOffset, $remap[$storage->getLiquid($offset)]);
				}
			}
		}

		foreach($source->getAllPositionData() as $offset => $data){
			[$x, $y, $z] = $storage->position($offset);
			[$newX, $newZ] = $rotation->rotatePosition($x, $z, $sizeX, $sizeZ);
			$palette->setPositionData($rotated->offset($newX, $y, $newZ), clone $data);
		}

		[$originX, $originY, $originZ] = $structure->getOrigin();
		return new Structure(
			$rotated,
			new PaletteSet([$structure->getPaletteName() => $palette]),
			[$originX, $originY, $originZ],
			[],
			$structure->getFormatVersion(),
			$structure->getCompression()
		);
	}
}
