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

namespace altay\bedrockstructure\tests\storage;

use altay\bedrockstructure\storage\BlockStorage;
use PHPUnit\Framework\TestCase;

final class BlockStorageTest extends TestCase{

	public function testOffsetAndPositionAreInverse() : void{
		$storage = BlockStorage::filled(3, 4, 5, 0);
		for($offset = 0; $offset < $storage->getVolume(); ++$offset){
			[$x, $y, $z] = $storage->position($offset);
			self::assertSame($offset, $storage->offset($x, $y, $z));
		}
	}

	public function testHasLiquids() : void{
		$storage = BlockStorage::filled(2, 1, 1, 0);
		self::assertFalse($storage->hasLiquids());
		$storage->setLiquid(1, 3);
		self::assertTrue($storage->hasLiquids());
	}

	public function testFitsPalette() : void{
		$storage = BlockStorage::filled(2, 1, 1, 0);
		$storage->setBlock(1, 2);
		self::assertTrue($storage->fitsPalette(3));
		self::assertFalse($storage->fitsPalette(2));
	}

	public function testRejectsWrongLayerSize() : void{
		$this->expectException(\InvalidArgumentException::class);
		new BlockStorage(2, 1, 1, [0], [-1, -1]);
	}
}
