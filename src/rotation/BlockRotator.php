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

use pocketmine\block\Block;
use pocketmine\block\Lever;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\HorizontalConnections;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\LeverFacing;
use pocketmine\block\utils\MultiAnyFacing;
use pocketmine\block\utils\PillarRotation;
use pocketmine\block\utils\SignLikeRotation;
use pocketmine\block\Wall;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use function array_map;

/**
 * Turns a block's own orientation along with the structure it's in. Covers the common orientation
 * states, anything more exotic (rails for example) keeps facing the way it did.
 */
final class BlockRotator{

	/**
	 * Mutates and returns the block, pass a copy if you still need the original.
	 */
	public function rotate(Block $block, Rotation $rotation) : Block{
		$rotate = $rotation->rotateFace(...);

		if($block instanceof HorizontalFacing || $block instanceof AnyFacing){
			$block->setFacing($rotate($block->getFacing()));
		}
		if($block instanceof PillarRotation && $block->getAxis() !== Axis::Y){
			$block->setAxis($block->getAxis() === Axis::X ? Axis::Z : Axis::X);
		}
		if($block instanceof SignLikeRotation){
			// 16 steps per full turn, so a quarter turn is 4
			$block->setRotation(($block->getRotation() + ($rotation->isClockwise() ? 4 : 12)) % 16);
		}
		if($block instanceof MultiAnyFacing){
			$block->setFaces(array_map($rotate, $block->getFaces()));
		}
		if($block instanceof HorizontalConnections){
			$block->setConnections(array_map($rotate, $block->getConnections()));
		}
		if($block instanceof Wall){
			$connections = $block->getConnections();
			foreach(Facing::HORIZONTAL as $face){
				$block->setConnection($face, null);
			}
			foreach($connections as $face => $type){
				$block->setConnection($rotate($face), $type);
			}
		}
		if($block instanceof Lever){
			$block->setFacing($this->rotateLever($block->getFacing(), $rotation->isClockwise()));
		}
		return $block;
	}

	private function rotateLever(LeverFacing $facing, bool $clockwise) : LeverFacing{
		return match($facing){
			LeverFacing::UP_AXIS_X => LeverFacing::UP_AXIS_Z,
			LeverFacing::UP_AXIS_Z => LeverFacing::UP_AXIS_X,
			LeverFacing::DOWN_AXIS_X => LeverFacing::DOWN_AXIS_Z,
			LeverFacing::DOWN_AXIS_Z => LeverFacing::DOWN_AXIS_X,
			LeverFacing::NORTH => $clockwise ? LeverFacing::EAST : LeverFacing::WEST,
			LeverFacing::EAST => $clockwise ? LeverFacing::SOUTH : LeverFacing::NORTH,
			LeverFacing::SOUTH => $clockwise ? LeverFacing::WEST : LeverFacing::EAST,
			LeverFacing::WEST => $clockwise ? LeverFacing::NORTH : LeverFacing::SOUTH,
		};
	}
}
