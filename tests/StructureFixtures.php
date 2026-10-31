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

use pocketmine\block\Block;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\nbt\TreeRoot;
use PHPUnit\Framework\Assert;
use function array_map;
use function zlib_encode;
use const ZLIB_ENCODING_DEFLATE;

/**
 * Hand rolled structure files and a few asserts shared by the tests.
 */
final class StructureFixtures{
	/** Block state version of 1.21.x, new enough that the upgrader leaves states alone. */
	public const CURRENT_STATE_VERSION = 18168865;

	private function __construct(){
		//NOOP
	}

	/**
	 * @param int[] $values
	 * @phpstan-return ListTag<IntTag>
	 */
	public static function intList(array $values) : ListTag{
		return new ListTag(array_map(fn(int $v) => new IntTag($v), $values), NBT::TAG_Int);
	}

	public static function stateTag(string $name, int $version = self::CURRENT_STATE_VERSION, CompoundTag $states = new CompoundTag()) : CompoundTag{
		return CompoundTag::create()
			->setString("name", $name)
			->setTag("states", $states)
			->setInt("version", $version);
	}

	/**
	 * Builds the bytes of a structure file by hand, the way the game lays it out.
	 *
	 * @param int[]         $size
	 * @param Tag[]         $layers
	 * @param CompoundTag[] $palette
	 * @param int|null      $compression compression tag to write, 1 also zlib compresses the structure tag
	 */
	public static function encode(int $version, array $size, array $layers, array $palette, int $layerType = NBT::TAG_List, ?int $compression = null) : string{
		$serializer = new LittleEndianNbtSerializer();
		$structure = CompoundTag::create()
			->setTag("block_indices", new ListTag($layers, $layerType))
			->setTag("entities", new ListTag([], NBT::TAG_Compound))
			->setTag("palette", CompoundTag::create()->setTag("default", CompoundTag::create()
				->setTag("block_palette", new ListTag($palette, NBT::TAG_Compound))
				->setTag("block_position_data", CompoundTag::create())
			));
		$root = CompoundTag::create()
			->setInt("format_version", $version)
			->setTag("size", self::intList($size))
			->setTag("structure_world_origin", self::intList([0, 0, 0]));
		if($compression !== null){
			$root->setInt("compression", $compression);
		}
		if($compression === 1){
			$root->setByteArray("structure", (string) zlib_encode($serializer->write(new TreeRoot($structure)), ZLIB_ENCODING_DEFLATE));
		}else{
			$root->setTag("structure", $structure);
		}
		return $serializer->write(new TreeRoot($root));
	}

	public static function encodeRoot(CompoundTag $root) : string{
		return (new LittleEndianNbtSerializer())->write(new TreeRoot($root));
	}

	public static function decodeRoot(string $data) : CompoundTag{
		return (new LittleEndianNbtSerializer())->read($data)->mustGetCompoundTag();
	}

	public static function assertSameBlock(?Block $expected, ?Block $actual) : void{
		Assert::assertSame($expected?->getStateId(), $actual?->getStateId());
	}
}
