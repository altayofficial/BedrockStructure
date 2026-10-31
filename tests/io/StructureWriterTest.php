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
use altay\bedrockstructure\io\FormatVersion;
use altay\bedrockstructure\io\StructureWriter;
use altay\bedrockstructure\Structure;
use altay\bedrockstructure\tests\StructureFixtures;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteArrayTag;
use PHPUnit\Framework\TestCase;

final class StructureWriterTest extends TestCase{

	public function testWritesVersion2ByDefault() : void{
		// a vanilla style version 1 file comes back out as version 2 with the empty liquid layer left out
		$data = StructureFixtures::encode(1, [1, 1, 2], [StructureFixtures::intList([0, -1]), StructureFixtures::intList([-1, -1])], [StructureFixtures::stateTag("minecraft:stone")]);
		$structure = Structure::read($data);
		self::assertSame(FormatVersion::V2, $structure->getFormatVersion());

		$root = StructureFixtures::decodeRoot((new StructureWriter())->write($structure));
		self::assertSame(2, $root->getInt("format_version"));
		self::assertNull($root->getTag("compression"));
		$layers = $root->getCompoundTag("structure")?->getListTag("block_indices");
		self::assertNotNull($layers);
		self::assertCount(1, $layers);
		self::assertSame(NBT::TAG_IntArray, $layers->getTagType());
	}

	public function testVersion1AlwaysWritesTwoLayers() : void{
		$structure = Structure::create(1, 1, 1);
		$structure->setFormatVersion(FormatVersion::V1);
		$layers = StructureFixtures::decodeRoot((new StructureWriter())->write($structure))->getCompoundTag("structure")?->getListTag("block_indices");
		self::assertNotNull($layers);
		self::assertCount(2, $layers);
		self::assertSame(NBT::TAG_List, $layers->getTagType());
	}

	public function testWritesCompressed() : void{
		$structure = Structure::create(1, 1, 1);
		$structure->setCompression(Compression::ZLIB);
		$root = StructureFixtures::decodeRoot((new StructureWriter())->write($structure));
		self::assertSame(1, $root->getInt("compression"));
		self::assertInstanceOf(ByteArrayTag::class, $root->getTag("structure"));
	}

	public function testCompressionNeedsVersion2() : void{
		$structure = Structure::create(1, 1, 1);
		$structure->setFormatVersion(FormatVersion::V1);
		$structure->setCompression(Compression::ZLIB);
		$this->expectException(\LogicException::class);
		(new StructureWriter())->write($structure);
	}
}
