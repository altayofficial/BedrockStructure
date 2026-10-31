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

namespace altay\bedrockstructure\tests\rotation;

use altay\bedrockstructure\rotation\Rotation;
use altay\bedrockstructure\rotation\StructureRotator;
use altay\bedrockstructure\Structure;
use altay\bedrockstructure\tests\StructureFixtures;
use pocketmine\block\Stair;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Wood;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\CompoundTag;
use PHPUnit\Framework\TestCase;

final class StructureRotatorTest extends TestCase{

	public function testRotation() : void{
		$stairs = VanillaBlocks::OAK_STAIRS()->setFacing(Facing::NORTH);
		$structure = Structure::create(2, 1, 3);
		$structure->setBlockAt(0, 0, 0, $stairs, null, CompoundTag::create()->setString("id", "Marker"));
		$structure->setBlockAt(1, 0, 2, VanillaBlocks::OAK_LOG()->setAxis(Axis::X));
		$rotator = new StructureRotator();

		$right = $rotator->rotate($structure, Rotation::CLOCKWISE);
		self::assertSame([3, 1, 2], $right->getDimensions());
		// north west corner ends up in the north east corner when turning clockwise
		$block = $right->getBlockAt(2, 0, 0);
		self::assertInstanceOf(Stair::class, $block);
		self::assertSame(Facing::EAST, $block->getFacing());
		self::assertNotNull($right->getBlockEntityDataAt(2, 0, 0));
		$log = $right->getBlockAt(0, 0, 1);
		self::assertInstanceOf(Wood::class, $log);
		self::assertSame(Axis::Z, $log->getAxis());

		$left = $rotator->rotate($structure, Rotation::COUNTER_CLOCKWISE);
		$block = $left->getBlockAt(0, 0, 1);
		self::assertInstanceOf(Stair::class, $block);
		self::assertSame(Facing::WEST, $block->getFacing());

		$back = $rotator->rotate($right, Rotation::COUNTER_CLOCKWISE);
		self::assertSame([2, 1, 3], $back->getDimensions());
		StructureFixtures::assertSameBlock($stairs, $back->getBlockAt(0, 0, 0));
		self::assertNotNull($back->getBlockEntityDataAt(0, 0, 0));
	}

	public function testFourTurnsGetBackToTheStart() : void{
		$structure = Structure::create(3, 2, 4);
		$structure->setBlockAt(0, 1, 3, VanillaBlocks::OAK_STAIRS()->setFacing(Facing::SOUTH), VanillaBlocks::WATER());
		$structure->setBlockAt(2, 0, 1, VanillaBlocks::OAK_SIGN()->setRotation(3));

		$rotated = $structure;
		for($i = 0; $i < 4; ++$i){
			$rotated = $rotated->rotateRight();
		}
		self::assertSame($structure->getDimensions(), $rotated->getDimensions());
		for($x = 0; $x < 3; ++$x){
			for($y = 0; $y < 2; ++$y){
				for($z = 0; $z < 4; ++$z){
					StructureFixtures::assertSameBlock($structure->getBlockAt($x, $y, $z), $rotated->getBlockAt($x, $y, $z));
					StructureFixtures::assertSameBlock($structure->getLiquidAt($x, $y, $z), $rotated->getLiquidAt($x, $y, $z));
				}
			}
		}
	}
}
