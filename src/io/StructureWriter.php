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

namespace altay\bedrockstructure\io;

use altay\bedrockstructure\Structure;
use altay\bedrockstructure\StructureException;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\Filesystem;
use function array_map;

/**
 * Encodes structures into .mcstructure files, using whatever format version and compression the
 * structure is set to.
 */
final class StructureWriter{
	private LittleEndianNbtSerializer $serializer;

	public function __construct(){
		$this->serializer = new LittleEndianNbtSerializer();
	}

	/**
	 * @throws \LogicException if compression is enabled on a format version that doesn't support it
	 */
	public function write(Structure $structure) : string{
		$version = $structure->getFormatVersion();
		$compression = $structure->getCompression();
		if($compression !== Compression::NONE && !$version->supportsCompression()){
			throw new \LogicException("Format version {$version->value} doesn't support compression");
		}

		$data = CompoundTag::create()
			->setTag(StructureTags::BLOCK_INDICES, $version->layerCodec()->encode($structure->getStorage()))
			->setTag(StructureTags::ENTITIES, new ListTag($structure->getEntities(), NBT::TAG_Compound))
			->setTag(StructureTags::PALETTE, $structure->getPalettes()->toNbt());

		$root = CompoundTag::create()->setInt(StructureTags::FORMAT_VERSION, $version->value);
		if($compression !== Compression::NONE){
			$root->setInt(StructureTags::COMPRESSION, $compression->value);
		}
		$root
			->setTag(StructureTags::SIZE, self::intList($structure->getDimensions()))
			->setTag(StructureTags::ORIGIN, self::intList($structure->getOrigin()))
			->setTag(StructureTags::STRUCTURE, $compression === Compression::NONE ?
				$data :
				new ByteArrayTag($compression->compress($this->serializer->write(new TreeRoot($data))))
			);

		return $this->serializer->write(new TreeRoot($root));
	}

	/**
	 * Writes the structure to a file, replacing it if it already exists.
	 *
	 * @throws StructureException
	 */
	public function writeFile(Structure $structure, string $path) : void{
		try{
			Filesystem::safeFilePutContents($path, $this->write($structure));
		}catch(\RuntimeException $e){
			throw new StructureException("Failed to write structure file $path: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param int[] $values
	 * @phpstan-return ListTag<IntTag>
	 */
	private static function intList(array $values) : ListTag{
		return new ListTag(array_map(fn(int $v) => new IntTag($v), $values), NBT::TAG_Int);
	}
}
