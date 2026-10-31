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

namespace altay\bedrockstructure\tests;

use altay\bedrockstructure\io\Compression;
use altay\bedrockstructure\io\FormatVersion;
use altay\bedrockstructure\Structure;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\nbt\tag\CompoundTag;
use PHPUnit\Framework\TestCase;

final class StructureTest extends TestCase{

	public function testCreateIsFilledWithAir() : void{
		$structure = Structure::create(2, 3, 4);
		self::assertSame([2, 3, 4], $structure->getDimensions());
		StructureFixtures::assertSameBlock(VanillaBlocks::AIR(), $structure->getBlockAt(1, 2, 3));
		self::assertNull($structure->getLiquidAt(1, 2, 3));
	}

	public function testCreateRejectsEmptySize() : void{
		$this->expectException(\InvalidArgumentException::class);
		Structure::create(0, 1, 1);
	}

	public function testOutOfBounds() : void{
		$this->expectException(\InvalidArgumentException::class);
		Structure::create(1, 1, 1)->getBlockAt(1, 0, 0);
	}

	public function testRoundTrip() : void{
		$structure = Structure::create(2, 2, 2);
		$chest = CompoundTag::create()->setString("id", "Chest")->setByte("Findable", 0);
		$structure->setBlockAt(0, 0, 0, VanillaBlocks::STONE());
		$structure->setBlockAt(1, 0, 1, VanillaBlocks::OAK_STAIRS(), VanillaBlocks::WATER());
		$structure->setBlockAt(0, 1, 1, VanillaBlocks::CHEST(), null, $chest);
		$structure->setBlockAt(1, 1, 1, null);
		$structure->setOrigin(10, 64, -3);

		foreach([[FormatVersion::V1, Compression::NONE], [FormatVersion::V2, Compression::NONE], [FormatVersion::V2, Compression::ZLIB]] as [$version, $compression]){
			$structure->setFormatVersion($version);
			$structure->setCompression($compression);
			$read = Structure::read($structure->write());

			self::assertSame($compression, $read->getCompression());
			self::assertSame([10, 64, -3], $read->getOrigin());
			StructureFixtures::assertSameBlock(VanillaBlocks::STONE(), $read->getBlockAt(0, 0, 0));
			StructureFixtures::assertSameBlock(VanillaBlocks::OAK_STAIRS(), $read->getBlockAt(1, 0, 1));
			StructureFixtures::assertSameBlock(VanillaBlocks::WATER(), $read->getLiquidAt(1, 0, 1));
			StructureFixtures::assertSameBlock(VanillaBlocks::CHEST(), $read->getBlockAt(0, 1, 1));
			self::assertTrue($chest->equals($read->getBlockEntityDataAt(0, 1, 1) ?? new CompoundTag()));
			self::assertNull($read->getBlockAt(1, 1, 1));
			StructureFixtures::assertSameBlock(VanillaBlocks::AIR(), $read->getBlockAt(1, 1, 0));
		}
	}

	public function testOverwritingDropsBlockEntityData() : void{
		$structure = Structure::create(1, 1, 1);
		$structure->setBlockAt(0, 0, 0, VanillaBlocks::CHEST(), null, CompoundTag::create()->setString("id", "Chest"));
		$structure->setBlockAt(0, 0, 0, VanillaBlocks::STONE());
		self::assertNull($structure->getBlockEntityDataAt(0, 0, 0));
	}

	public function testUpgradesOldStates() : void{
		// 1.16 era stone with a stone_type state, which got flattened into minecraft:granite later on
		$states = CompoundTag::create()->setString("stone_type", "granite");
		$data = StructureFixtures::encode(1, [1, 1, 1], [StructureFixtures::intList([0]), StructureFixtures::intList([-1])], [StructureFixtures::stateTag("minecraft:stone", 17959425, $states)]);
		StructureFixtures::assertSameBlock(VanillaBlocks::GRANITE(), Structure::read($data)->getBlockAt(0, 0, 0));
	}

	public function testUnknownBlocksSurviveRoundTrip() : void{
		$data = StructureFixtures::encode(1, [1, 1, 1], [StructureFixtures::intList([0]), StructureFixtures::intList([-1])], [StructureFixtures::stateTag("altay:not_a_block")]);
		$structure = Structure::read($data);
		self::assertSame(BlockTypeIds::INFO_UPDATE, $structure->getBlockAt(0, 0, 0)?->getTypeId());

		$rotated = Structure::read($structure->rotateRight()->write());
		self::assertSame("altay:not_a_block", $rotated->getBlockStateDataAt(0, 0, 0)?->getName());
	}

	public function testMissingDefaultPaletteUsesFirstOne() : void{
		$structure = Structure::create(1, 1, 1);
		$structure->usePalette("other");
		$structure->setBlockAt(0, 0, 0, VanillaBlocks::STONE());
		$root = StructureFixtures::decodeRoot($structure->write());
		$root->getCompoundTag("structure")?->getCompoundTag("palette")?->removeTag("default");

		$read = Structure::read(StructureFixtures::encodeRoot($root));
		self::assertSame("other", $read->getPaletteName());
		StructureFixtures::assertSameBlock(VanillaBlocks::STONE(), $read->getBlockAt(0, 0, 0));
	}
}
