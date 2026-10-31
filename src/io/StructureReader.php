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

use altay\bedrockstructure\palette\PaletteSet;
use altay\bedrockstructure\storage\BlockStorage;
use altay\bedrockstructure\Structure;
use altay\bedrockstructure\StructureException;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NbtException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\utils\Filesystem;
use function array_fill;
use function count;

/**
 * Decodes .mcstructure files, format version 1 and 2, compressed or not.
 */
final class StructureReader{
	private LittleEndianNbtSerializer $serializer;

	public function __construct(){
		$this->serializer = new LittleEndianNbtSerializer();
	}

	/**
	 * @throws StructureException
	 */
	public function read(string $data) : Structure{
		try{
			return $this->fromNbt($this->serializer->read($data)->mustGetCompoundTag());
		}catch(NbtException $e){
			throw new StructureException("Failed to decode structure: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws StructureException
	 */
	public function readFile(string $path) : Structure{
		try{
			$data = Filesystem::fileGetContents($path);
		}catch(\RuntimeException $e){
			throw new StructureException("Failed to read structure file $path: " . $e->getMessage(), 0, $e);
		}
		return $this->read($data);
	}

	/**
	 * @throws StructureException
	 * @throws NbtException
	 */
	private function fromNbt(CompoundTag $root) : Structure{
		$rawVersion = $root->getInt(StructureTags::FORMAT_VERSION);
		$version = FormatVersion::tryFrom($rawVersion) ?? throw new StructureException("Unsupported format version $rawVersion");

		$rawCompression = $root->getInt(StructureTags::COMPRESSION, Compression::NONE->value);
		$compression = Compression::tryFrom($rawCompression);
		if($compression === null || ($compression !== Compression::NONE && !$version->supportsCompression())){
			throw new StructureException("Unsupported compression $rawCompression for format version $rawVersion");
		}

		[$sizeX, $sizeY, $sizeZ] = $this->readVector($root, StructureTags::SIZE);
		$origin = $this->readVector($root, StructureTags::ORIGIN);
		if($sizeX <= 0 || $sizeY <= 0 || $sizeZ <= 0){
			throw new StructureException("Structure size must be positive, got {$sizeX}x{$sizeY}x{$sizeZ}");
		}
		$volume = $sizeX * $sizeY * $sizeZ;

		$structure = $this->readStructureTag($root, $compression);

		$layers = $version->layerCodec()->decode($structure->getListTag(StructureTags::BLOCK_INDICES) ?? new ListTag());
		foreach($layers as $i => $layer){
			if(count($layer) !== $volume){
				throw new StructureException("Structure is {$sizeX}x{$sizeY}x{$sizeZ} and should have $volume blocks, but got " . count($layer) . " in layer $i");
			}
		}
		// version 2 can leave out the liquid layer, we add one anyway so the rest of the code can assume it exists
		$storage = new BlockStorage($sizeX, $sizeY, $sizeZ, $layers[0], $layers[1] ?? array_fill(0, $volume, BlockStorage::NO_BLOCK));

		$palettes = PaletteSet::fromNbt($structure->getCompoundTag(StructureTags::PALETTE) ?? new CompoundTag());
		$paletteSize = $palettes->getActive()->count();
		if(!$storage->fitsPalette($paletteSize)){
			throw new StructureException("Block indices point outside of the palette (size $paletteSize)");
		}

		// the version only matters for decoding, writing always starts out at version 2
		return new Structure($storage, $palettes, $origin, $this->readEntities($structure), FormatVersion::V2, $compression);
	}

	/**
	 * @throws StructureException
	 * @throws NbtException
	 */
	private function readStructureTag(CompoundTag $root, Compression $compression) : CompoundTag{
		if($compression === Compression::NONE){
			return $root->getCompoundTag(StructureTags::STRUCTURE) ?? throw new StructureException("Missing \"" . StructureTags::STRUCTURE . "\" tag");
		}
		$data = $compression->decompress($root->getByteArray(StructureTags::STRUCTURE));
		return $this->serializer->read($data)->mustGetCompoundTag();
	}

	/**
	 * @phpstan-return array{int, int, int}
	 * @throws StructureException
	 */
	private function readVector(CompoundTag $tag, string $name) : array{
		$values = $tag->getListTag($name)?->cast(IntTag::class)?->getAllValues();
		if($values === null || count($values) !== 3){
			throw new StructureException("\"$name\" must be a list of 3 ints");
		}
		/** @phpstan-var array{int, int, int} $values */
		return $values;
	}

	/**
	 * @return CompoundTag[]
	 * @phpstan-return list<CompoundTag>
	 * @throws StructureException
	 */
	private function readEntities(CompoundTag $structure) : array{
		$entities = [];
		foreach($structure->getListTag(StructureTags::ENTITIES)?->getValue() ?? [] as $entity){
			if(!$entity instanceof CompoundTag){
				throw new StructureException("Entities must be compounds");
			}
			$entities[] = $entity;
		}
		return $entities;
	}
}
