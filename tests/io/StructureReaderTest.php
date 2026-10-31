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

namespace altay\bedrockstructure\tests\io;

use altay\bedrockstructure\io\Compression;
use altay\bedrockstructure\io\StructureReader;
use altay\bedrockstructure\StructureException;
use altay\bedrockstructure\tests\StructureFixtures;
use pocketmine\block\VanillaBlocks;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\IntArrayTag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StructureReaderTest extends TestCase{

	public function testReadsIntArrayLayersWithoutLiquids() : void{
		$data = StructureFixtures::encode(2, [1, 1, 2], [new IntArrayTag([0, -1])], [StructureFixtures::stateTag("minecraft:polished_andesite")], NBT::TAG_IntArray);
		$structure = (new StructureReader())->read($data);
		StructureFixtures::assertSameBlock(VanillaBlocks::POLISHED_ANDESITE(), $structure->getBlockAt(0, 0, 0));
		self::assertNull($structure->getBlockAt(0, 0, 1));
		self::assertNull($structure->getLiquidAt(0, 0, 0));
	}

	public function testReadsCompressed() : void{
		$data = StructureFixtures::encode(2, [1, 1, 1], [new IntArrayTag([0])], [StructureFixtures::stateTag("minecraft:stone")], NBT::TAG_IntArray, 1);
		$structure = (new StructureReader())->read($data);
		self::assertSame(Compression::ZLIB, $structure->getCompression());
		StructureFixtures::assertSameBlock(VanillaBlocks::STONE(), $structure->getBlockAt(0, 0, 0));
	}

	/**
	 * @return \Generator<string, array{string}>
	 */
	public static function malformedProvider() : \Generator{
		$air = [StructureFixtures::stateTag("minecraft:air")];
		$v1 = fn(array $size, array $blocks) => StructureFixtures::encode(1, $size, [StructureFixtures::intList($blocks), StructureFixtures::intList($blocks)], $air);
		yield "unsupported version" => [StructureFixtures::encode(3, [1, 1, 1], [StructureFixtures::intList([0])], $air)];
		yield "size with 2 values" => [$v1([1, 1], [0])];
		yield "zero size" => [$v1([0, 1, 1], [])];
		yield "negative size" => [$v1([-1, -1, 1], [0])];
		yield "too few indices" => [$v1([2, 1, 1], [0])];
		yield "no layers" => [StructureFixtures::encode(1, [1, 1, 1], [], $air)];
		yield "index past palette" => [$v1([1, 1, 1], [1])];
		yield "index below -1" => [$v1([1, 1, 1], [-2])];
		yield "garbage" => ["\x00\x01\x02"];

		$stone = [StructureFixtures::stateTag("minecraft:stone")];
		yield "v1 with one layer" => [StructureFixtures::encode(1, [1, 1, 1], [StructureFixtures::intList([0])], $stone)];
		yield "v1 with int array layers" => [StructureFixtures::encode(1, [1, 1, 1], [new IntArrayTag([0]), new IntArrayTag([-1])], $stone, NBT::TAG_IntArray)];
		yield "v2 with list layers" => [StructureFixtures::encode(2, [1, 1, 1], [StructureFixtures::intList([0])], $stone)];
		yield "v2 with three layers" => [StructureFixtures::encode(2, [1, 1, 1], [new IntArrayTag([0]), new IntArrayTag([-1]), new IntArrayTag([-1])], $stone, NBT::TAG_IntArray)];
		yield "compression on v1" => [StructureFixtures::encode(1, [1, 1, 1], [StructureFixtures::intList([0]), StructureFixtures::intList([-1])], $stone, NBT::TAG_List, 1)];
		yield "unknown compression" => [StructureFixtures::encode(2, [1, 1, 1], [new IntArrayTag([0])], $stone, NBT::TAG_IntArray, 2)];
		$plain = StructureFixtures::decodeRoot(StructureFixtures::encode(2, [1, 1, 1], [new IntArrayTag([0])], $stone, NBT::TAG_IntArray));
		yield "compressed but not a byte array" => [StructureFixtures::encodeRoot((clone $plain)->setInt("compression", 1))];
		yield "corrupt zlib data" => [StructureFixtures::encodeRoot((clone $plain)->setInt("compression", 1)->setByteArray("structure", "definitely not zlib"))];
	}

	#[DataProvider('malformedProvider')]
	public function testMalformed(string $data) : void{
		$this->expectException(StructureException::class);
		(new StructureReader())->read($data);
	}
}
