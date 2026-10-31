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

namespace altay\bedrockstructure\palette;

use altay\bedrockstructure\StructureException;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function count;
use function is_int;

/**
 * One named palette of a structure: the block states that block indices point at, plus the extra data
 * (mostly block entity NBT) stored for specific offsets.
 *
 * Every entry is upgraded and resolved to a runtime state ID once when loaded, so lookups during
 * getBlockAt() stay cheap.
 *
 * @internal
 */
final class Palette{
	public const TAG_BLOCK_PALETTE = "block_palette";
	private const TAG_BLOCK_POSITION_DATA = "block_position_data";
	private const TAG_BLOCK_ENTITY_DATA = "block_entity_data";

	/**
	 * @var BlockStateData[]
	 * @phpstan-var list<BlockStateData>
	 */
	private array $states = [];
	/**
	 * Runtime state ID for every palette index.
	 * @var int[]
	 * @phpstan-var list<int>
	 */
	private array $stateIds = [];
	/**
	 * Indices whose state couldn't be deserialized and fell back to the unknown block.
	 * @var true[]
	 * @phpstan-var array<int, true>
	 */
	private array $unknown = [];
	/**
	 * @var int[]
	 * @phpstan-var array<int, int>
	 */
	private array $lookup = [];
	/**
	 * @var CompoundTag[]
	 * @phpstan-var array<int, CompoundTag>
	 */
	private array $positionData = [];

	/**
	 * Creates a palette holding only air, which is what new structures start out with.
	 */
	public static function create() : self{
		$palette = new self();
		$palette->indexOf(VanillaBlocks::AIR());
		return $palette;
	}

	/**
	 * @throws StructureException
	 */
	public static function fromNbt(CompoundTag $tag) : self{
		$palette = new self();

		$entries = $tag->getTag(self::TAG_BLOCK_PALETTE);
		if($entries !== null){
			if(!$entries instanceof ListTag || ($entries->count() > 0 && $entries->getTagType() !== NBT::TAG_Compound)){
				throw new StructureException("\"" . self::TAG_BLOCK_PALETTE . "\" must be a list of compounds");
			}
			$upgrader = GlobalBlockStateHandlers::getUpgrader();
			foreach($entries as $entry){
				/** @var CompoundTag $entry */
				try{
					$palette->add($upgrader->upgradeBlockStateNbt($entry));
				}catch(BlockStateDeserializeException $e){
					throw new StructureException("Invalid palette entry: " . $e->getMessage(), 0, $e);
				}
			}
		}

		$positionData = $tag->getTag(self::TAG_BLOCK_POSITION_DATA);
		if($positionData !== null){
			if(!$positionData instanceof CompoundTag){
				throw new StructureException("\"" . self::TAG_BLOCK_POSITION_DATA . "\" must be a compound");
			}
			foreach($positionData->getValue() as $offset => $data){
				// yeah, the offsets are stringified ints, PHP already turned the valid ones back into int keys for us
				if(!is_int($offset) || $offset < 0){
					throw new StructureException("Invalid block position data offset \"$offset\"");
				}
				if(!$data instanceof CompoundTag){
					throw new StructureException("Block position data at offset $offset must be a compound");
				}
				$palette->positionData[$offset] = $data;
			}
		}

		if(count($palette->states) === 0){
			$palette->indexOf(VanillaBlocks::AIR());
		}
		return $palette;
	}

	public function toNbt() : CompoundTag{
		$entries = [];
		foreach($this->states as $state){
			$entries[] = $state->toVanillaNbt();
		}
		$positionData = CompoundTag::create();
		foreach($this->positionData as $offset => $data){
			$positionData->setTag((string) $offset, $data);
		}
		return CompoundTag::create()
			->setTag(self::TAG_BLOCK_PALETTE, new ListTag($entries, NBT::TAG_Compound))
			->setTag(self::TAG_BLOCK_POSITION_DATA, $positionData);
	}

	public function count() : int{
		return count($this->states);
	}

	public function getStateData(int $index) : BlockStateData{
		return $this->states[$index] ?? throw new \InvalidArgumentException("No palette entry at index $index");
	}

	/**
	 * Returns the block at the palette index. Entries the server doesn't know come back as the unknown
	 * (info_update) block, check isUnknown() if that matters.
	 */
	public function getBlock(int $index) : Block{
		$stateId = $this->stateIds[$index] ?? throw new \InvalidArgumentException("No palette entry at index $index");
		return RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId);
	}

	public function isUnknown(int $index) : bool{
		return isset($this->unknown[$index]);
	}

	/**
	 * Returns the palette index of the block, adding it to the palette if it isn't in there yet.
	 */
	public function indexOf(Block $block) : int{
		$stateId = $block->getStateId();
		if(isset($this->lookup[$stateId])){
			return $this->lookup[$stateId];
		}
		return $this->add(GlobalBlockStateHandlers::getSerializer()->serializeBlock($block), $stateId);
	}

	/**
	 * Appends raw state data to the palette without looking for an existing entry. Handy for carrying
	 * over states this server can't deserialize, so they survive a read/write round trip untouched.
	 */
	public function add(BlockStateData $state, ?int $stateId = null) : int{
		$index = count($this->states);
		if($stateId === null){
			try{
				$stateId = GlobalBlockStateHandlers::getDeserializer()->deserialize($state);
			}catch(BlockStateDeserializeException){
				// hmm, unknown or unsupported block, keep the raw state so writing it back loses nothing
				$stateId = GlobalBlockStateHandlers::getDeserializer()->deserialize(GlobalBlockStateHandlers::getUnknownBlockStateData());
				$this->unknown[$index] = true;
			}
		}
		$this->states[] = $state;
		$this->stateIds[] = $stateId;
		if(!isset($this->unknown[$index])){
			$this->lookup[$stateId] ??= $index;
		}
		return $index;
	}

	public function getPositionData(int $offset) : ?CompoundTag{
		return $this->positionData[$offset] ?? null;
	}

	public function setPositionData(int $offset, ?CompoundTag $data) : void{
		if($data === null){
			unset($this->positionData[$offset]);
		}else{
			$this->positionData[$offset] = $data;
		}
	}

	/**
	 * Returns a copy of the block entity NBT stored for the offset, if any.
	 */
	public function getBlockEntityData(int $offset) : ?CompoundTag{
		$data = ($this->positionData[$offset] ?? null)?->getCompoundTag(self::TAG_BLOCK_ENTITY_DATA);
		return $data !== null ? clone $data : null;
	}

	/**
	 * Replaces everything stored for the offset with just this block entity NBT, or clears it on null.
	 */
	public function setBlockEntityData(int $offset, ?CompoundTag $data) : void{
		$this->setPositionData($offset, $data === null ? null : CompoundTag::create()->setTag(self::TAG_BLOCK_ENTITY_DATA, clone $data));
	}

	/**
	 * @return CompoundTag[]
	 * @phpstan-return array<int, CompoundTag>
	 */
	public function getAllPositionData() : array{
		return $this->positionData;
	}
}
