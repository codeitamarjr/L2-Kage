/*
 * Copyright (c) 2013 L2jMobius
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
 * IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
package org.l2jmobius.gameserver.network.serverpackets.relics;

import java.util.ArrayList;
import java.util.Collection;
import java.util.List;

import org.l2jmobius.commons.network.buffer.WriteBuffer;
import org.l2jmobius.commons.util.Rnd;
import org.l2jmobius.gameserver.config.RelicSystemConfig;
import org.l2jmobius.gameserver.data.holders.RelicCouponHolder;
import org.l2jmobius.gameserver.data.holders.RelicDataHolder;
import org.l2jmobius.gameserver.data.xml.RelicCouponData;
import org.l2jmobius.gameserver.data.xml.RelicData;
import org.l2jmobius.gameserver.entity.World;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.holders.player.PlayerRelicData;
import org.l2jmobius.gameserver.mechanics.variables.AccountVariables;
import org.l2jmobius.gameserver.network.GameClient;
import org.l2jmobius.gameserver.network.PacketLogger;
import org.l2jmobius.gameserver.network.ServerPackets;
import org.l2jmobius.gameserver.network.serverpackets.ServerPacket;

/**
 * @author CostyKiller, Mobius
 */
public class ExRelicsSummonResult extends ServerPacket
{
	private final Player _player;
	private final int _relicCouponItemId;
	private final int _relicSummonCount;
	
	public ExRelicsSummonResult(Player player, int relicCouponItemId, int relicSummonCount)
	{
		_player = player;
		_relicCouponItemId = relicCouponItemId;
		_relicSummonCount = player.getInventory().getItemByItemId(_relicCouponItemId) == null ? 0 : relicSummonCount;
	}
	
	@Override
	public void writeImpl(GameClient client, WriteBuffer buffer)
	{
		ServerPackets.EX_RELICS_SUMMON_RESULT.writeId(this, buffer);
		
		buffer.writeByte(true); // Only works with true.
		buffer.writeInt(_relicCouponItemId); // Summon item id.
		buffer.writeInt(_relicSummonCount); // Array size of obtained relics.
		
		final RelicCouponHolder relicCouponHolder = RelicCouponData.getInstance().getRelicIdFromCouponId(_relicCouponItemId);
		if (relicCouponHolder != null)
		{
			final int obtainedRelicId = relicCouponHolder.getRelicId();
			final RelicDataHolder obtainedRelicTemplate = RelicData.getInstance().getRelic(obtainedRelicId);
			if (obtainedRelicTemplate != null)
			{
				buffer.writeInt(obtainedRelicId);
				
				// Add to database table the obtained relics.
				final Collection<PlayerRelicData> storedRelics = _player.getRelics();
				
				// Check if the relic with the same ID exists.
				PlayerRelicData existingRelic = null;
				for (PlayerRelicData relic : storedRelics)
				{
					if (relic.getRelicId() == obtainedRelicId)
					{
						existingRelic = relic;
						break;
					}
				}
				
				final PlayerRelicData newRelic = new PlayerRelicData(obtainedRelicId, 0, 0, 0, 0);
				newRelic.setRelicCount(1);
				newRelic.setRelicSummonTime(System.currentTimeMillis());
				if (existingRelic != null)
				{
					// Check indexes of relics with same id to avoid duplicate 300+ index.
					final List<Integer> unconfirmedRelics = new ArrayList<>();
					final Collection<PlayerRelicData> storedRelics2 = _player.getRelics();
					for (PlayerRelicData relic2 : storedRelics2)
					{
						if ((relic2.getRelicIndex() >= 300) && (relic2.getRelicId() == existingRelic.getRelicId())) // Unconfirmed relics are set on summon to index 300.
						{
							unconfirmedRelics.add(relic2.getRelicIndex());
						}
					}
					
					newRelic.setRelicIndex(_player.getNextPendingRelicIndex(newRelic.getRelicId()));
					
					// Increase the unconfirmed relics variable count.
					_player.getAccountVariables().set(AccountVariables.UNCONFIRMED_RELICS_COUNT, _player.getAccountVariables().getInt(AccountVariables.UNCONFIRMED_RELICS_COUNT, 0) + 1);
					_player.getAccountVariables().storeMe();
					
					if (RelicSystemConfig.RELIC_SUMMON_ANNOUNCE)
					{
						World.broadcastToAllOnlinePlayers(new ExRelicsAnnounce(_player, newRelic.getRelicId()));
					}
				}
				
				storedRelics.add(newRelic);
				
				_player.storeRelics();
				_player.sendPacket(new ExRelicsList(_player)); // Update confirmed relic list relics count.
				_player.sendPacket(new ExRelicsExchangeList(_player)); // Update relic exchange/confirm list.
				// _player.giveRelicSkill(obtainedRelicTemplate);
				return;
			}
		}
		
		// Obtained relics by scroll type.
		int obtainedRelicId = 0;
		final List<PlayerRelicData> updatedRelics = new ArrayList<>();
		for (int i = 1; i <= _relicSummonCount; i++)
		{
			if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
			{
				_player.sendMessage("I = " + i);
			}
			
			// Relic Summon Coupon (relic of No-grade / D-grade / C-grade).
			if (RelicSystemConfig.RELIC_SUMMON_COUPONS.contains(_relicCouponItemId))
			{
				final int relicChance = Rnd.get(100);
				final int shiningRelicChance = Rnd.get(100);
				if (relicChance <= RelicSystemConfig.RELIC_SUMMON_COMMON_COUPON_CHANCE_C_GRADE)
				{
					if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_C_GRADE)
					{
						obtainedRelicId = RelicSystemConfig.C_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.C_GRADE_SHINING_RELICS.size()));
					}
					else
					{
						obtainedRelicId = RelicSystemConfig.C_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.C_GRADE_COMMON_RELICS.size()));
					}
				}
				else if (relicChance <= RelicSystemConfig.RELIC_SUMMON_COMMON_COUPON_CHANCE_D_GRADE)
				{
					if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_D_GRADE)
					{
						obtainedRelicId = RelicSystemConfig.D_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.D_GRADE_SHINING_RELICS.size()));
					}
					else
					{
						obtainedRelicId = RelicSystemConfig.D_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.D_GRADE_COMMON_RELICS.size()));
					}
				}
				else
				{
					obtainedRelicId = RelicSystemConfig.NO_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.NO_GRADE_COMMON_RELICS.size()));
				}
			}
			// Shining Relic Summon Coupon (relics of No-grade / D-grade / C-grade / B-grade).
			else if (RelicSystemConfig.SHINING_RELIC_SUMMON_COUPONS.contains(_relicCouponItemId))
			{
				final int relicChance = Rnd.get(100);
				final int shiningRelicChance = Rnd.get(100);
				if (relicChance <= RelicSystemConfig.RELIC_SUMMON_SHINING_COUPON_CHANCE_B_GRADE)
				{
					if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_B_GRADE)
					{
						obtainedRelicId = RelicSystemConfig.B_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.B_GRADE_SHINING_RELICS.size()));
					}
					else
					{
						obtainedRelicId = RelicSystemConfig.B_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.B_GRADE_COMMON_RELICS.size()));
					}
				}
				else if (relicChance <= RelicSystemConfig.RELIC_SUMMON_SHINING_COUPON_CHANCE_C_GRADE)
				{
					if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_C_GRADE)
					{
						obtainedRelicId = RelicSystemConfig.C_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.C_GRADE_SHINING_RELICS.size()));
					}
					else
					{
						obtainedRelicId = RelicSystemConfig.C_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.C_GRADE_COMMON_RELICS.size()));
					}
				}
				else if (relicChance <= RelicSystemConfig.RELIC_SUMMON_SHINING_COUPON_CHANCE_D_GRADE)
				{
					if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_D_GRADE)
					{
						obtainedRelicId = RelicSystemConfig.D_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.D_GRADE_SHINING_RELICS.size()));
					}
					else
					{
						obtainedRelicId = RelicSystemConfig.D_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.D_GRADE_COMMON_RELICS.size()));
					}
				}
				else
				{
					obtainedRelicId = RelicSystemConfig.NO_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.NO_GRADE_COMMON_RELICS.size()));
				}
			}
			// C-grade Relic Summon Coupon (relics of C-grade).
			else if (RelicSystemConfig.C_GRADE_RELIC_SUMMON_COUPONS.contains(_relicCouponItemId))
			{
				final int shiningRelicChance = Rnd.get(100);
				if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_C_GRADE)
				{
					obtainedRelicId = RelicSystemConfig.C_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.C_GRADE_SHINING_RELICS.size()));
				}
				else
				{
					obtainedRelicId = RelicSystemConfig.C_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.C_GRADE_COMMON_RELICS.size()));
				}
			}
			// B-grade Relic Summon Coupon (relics of B-grade).
			else if (RelicSystemConfig.B_GRADE_RELIC_SUMMON_COUPONS.contains(_relicCouponItemId))
			{
				final int shiningRelicChance = Rnd.get(100);
				if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_B_GRADE)
				{
					obtainedRelicId = RelicSystemConfig.B_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.B_GRADE_SHINING_RELICS.size()));
				}
				else
				{
					obtainedRelicId = RelicSystemConfig.B_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.B_GRADE_COMMON_RELICS.size()));
				}
			}
			// A-grade Relic Summon Coupon (relics of A-grade).
			else if (RelicSystemConfig.A_GRADE_RELIC_SUMMON_COUPONS.contains(_relicCouponItemId))
			{
				obtainedRelicId = RelicSystemConfig.A_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.A_GRADE_COMMON_RELICS.size()));
			}
			// C-grade Relic Ticket (relics of D-grade / C-grade).
			else if (RelicSystemConfig.C_GRADE_RELIC_TICKETS.contains(_relicCouponItemId))
			{
				final int relicChance = Rnd.get(100);
				final int shiningRelicChance = Rnd.get(100);
				if (relicChance < RelicSystemConfig.RELIC_SUMMON_C_TICKET_CHANCE_C_GRADE)
				{
					obtainedRelicId = RelicSystemConfig.C_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.C_GRADE_COMMON_RELICS.size()));
				}
				else
				{
					if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_D_GRADE)
					{
						obtainedRelicId = RelicSystemConfig.D_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.D_GRADE_SHINING_RELICS.size()));
					}
					else
					{
						obtainedRelicId = RelicSystemConfig.D_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.D_GRADE_COMMON_RELICS.size()));
					}
				}
			}
			// B-grade Relic Ticket (relics of C-grade / B-grade).
			else if (RelicSystemConfig.B_GRADE_RELIC_TICKETS.contains(_relicCouponItemId))
			{
				final int relicChance = Rnd.get(100);
				final int shiningRelicChance = Rnd.get(100);
				if (relicChance < RelicSystemConfig.RELIC_SUMMON_B_TICKET_CHANCE_B_GRADE)
				{
					if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_B_GRADE)
					{
						obtainedRelicId = RelicSystemConfig.B_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.B_GRADE_SHINING_RELICS.size()));
					}
					else
					{
						obtainedRelicId = RelicSystemConfig.B_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.B_GRADE_COMMON_RELICS.size()));
					}
				}
				else
				{
					if (shiningRelicChance <= RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_C_GRADE)
					{
						obtainedRelicId = RelicSystemConfig.C_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.C_GRADE_SHINING_RELICS.size()));
					}
					else
					{
						obtainedRelicId = RelicSystemConfig.C_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.C_GRADE_COMMON_RELICS.size()));
					}
				}
			}
			// A-grade Relic Ticket (relics of B-grade / A-grade).
			else if (RelicSystemConfig.A_GRADE_RELIC_TICKETS.contains(_relicCouponItemId))
			{
				final int relicChance = Rnd.get(100);
				final int shiningRelicChance = Rnd.get(100);
				if (relicChance < RelicSystemConfig.RELIC_SUMMON_A_TICKET_CHANCE_A_GRADE)
				{
					obtainedRelicId = RelicSystemConfig.A_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.A_GRADE_COMMON_RELICS.size()));
				}
				else
				{
					if (shiningRelicChance < RelicSystemConfig.RELIC_SUMMON_CHANCE_SHINING_B_GRADE)
					{
						obtainedRelicId = RelicSystemConfig.B_GRADE_SHINING_RELICS.get(Rnd.get(RelicSystemConfig.B_GRADE_SHINING_RELICS.size()));
					}
					else
					{
						obtainedRelicId = RelicSystemConfig.B_GRADE_COMMON_RELICS.get(Rnd.get(RelicSystemConfig.B_GRADE_COMMON_RELICS.size()));
					}
				}
			}
			
			buffer.writeInt(obtainedRelicId);
			
			// Add to database table the obtained relics.
			Collection<PlayerRelicData> storedRelics = _player.getRelics();
			
			// Check if the relic with the same ID exists.
			PlayerRelicData existingRelic = null;
			for (PlayerRelicData relic : storedRelics)
			{
				if (relic.getRelicId() == obtainedRelicId)
				{
					existingRelic = relic;
					break;
				}
			}
			
			final RelicDataHolder obtainedRelicTemplate = RelicData.getInstance().getRelic(obtainedRelicId);
			if (obtainedRelicTemplate != null)
			{
				final PlayerRelicData newRelic = new PlayerRelicData(obtainedRelicId, 0, 0, 0, 0);
				final int obtainedRelicGrade = obtainedRelicTemplate.getGrade();
				if (existingRelic != null)
				{
					// A/B Grade relics need to be added to confirmation list first.
					if ((obtainedRelicGrade == 4) || (obtainedRelicGrade == 5))
					{
						// Check indexes of relics with same id to avoid duplicate 300+ index.
						final List<Integer> unconfirmedRelics = new ArrayList<>();
						final Collection<PlayerRelicData> storedRelics2 = _player.getRelics();
						for (PlayerRelicData relic2 : storedRelics2)
						{
							if ((relic2.getRelicIndex() >= 300) && (relic2.getRelicId() == existingRelic.getRelicId())) // Unconfirmed relics are set on summon to index 300.
							{
								unconfirmedRelics.add(relic2.getRelicIndex());
							}
						}
						
						if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
						{
							_player.sendMessage("0.Duplicate relic indexes list: " + unconfirmedRelics);
						}
						
						newRelic.setRelicCount(1);
						newRelic.setRelicIndex(_player.getNextPendingRelicIndex(newRelic.getRelicId()));
						newRelic.setRelicSummonTime(System.currentTimeMillis());
						storedRelics.add(newRelic);
						
						// Increase the unconfirmed relics variable count.
						_player.getAccountVariables().set(AccountVariables.UNCONFIRMED_RELICS_COUNT, _player.getAccountVariables().getInt(AccountVariables.UNCONFIRMED_RELICS_COUNT, 0) + 1);
						_player.getAccountVariables().storeMe();
						_player.storeRelics();
						_player.sendPacket(new ExRelicsExchangeList(_player)); // Update relic exchange/confirm list.
						if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
						{
							_player.sendMessage("1.Duplicate relic id: " + newRelic.getRelicId() + " was added to confirmation list.");
						}
						
						if (RelicSystemConfig.RELIC_SUMMON_ANNOUNCE)
						{
							// Announce new the obtained relic.
							World.broadcastToAllOnlinePlayers(new ExRelicsAnnounce(_player, newRelic.getRelicId()));
						}
					}
					// Update existing relics if not A/B Grade relics.
					else if (!((obtainedRelicGrade == 4) || (obtainedRelicGrade == 5)))
					{
						existingRelic.setRelicCount(existingRelic.getRelicCount() + 1);
						_player.storeRelics();
						updatedRelics.add(existingRelic);
						if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
						{
							_player.sendMessage("2.Existing relic id: " + existingRelic.getRelicId() + " count was updated.");
						}
						
						// Announce the existing obtained relic.
						if (RelicSystemConfig.RELIC_SUMMON_ANNOUNCE && !RelicSystemConfig.RELIC_ANNOUNCE_ONLY_A_B_GRADE)
						{
							World.broadcastToAllOnlinePlayers(new ExRelicsAnnounce(_player, existingRelic.getRelicId()));
						}
						
						// Check if relic is already registered in some collection.
						if (!_player.isRelicRegistered(existingRelic.getRelicId(), existingRelic.getRelicLevel()))
						{
							// Auto-Add to relic collections on summon.
							_player.sendPacket(new ExRelicsCollectionUpdate(_player, existingRelic.getRelicId(), existingRelic.getRelicLevel())); // Update collection list.
						}
					}
				}
				else
				{
					// A/B Grade relics need to be confirmed before add them to relics list.
					if ((obtainedRelicGrade == 4) || (obtainedRelicGrade == 5))
					{
						// Set Relic Index to 300 to be able to get the list of confirmation relics later.
						newRelic.setRelicCount(1);
						newRelic.setRelicIndex(300);
						newRelic.setRelicSummonTime(System.currentTimeMillis());
						storedRelics.add(newRelic);
						_player.storeRelics();
						_player.getAccountVariables().set(AccountVariables.UNCONFIRMED_RELICS_COUNT, _player.getAccountVariables().getInt(AccountVariables.UNCONFIRMED_RELICS_COUNT, 0) + 1);
						_player.getAccountVariables().storeMe();
						_player.sendPacket(new ExRelicsExchangeList(_player)); // Update relic exchange/confirm list.
						if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
						{
							_player.sendMessage("1.New relic id: " + newRelic.getRelicId() + " was added to confirmation list.");
						}
						
						if (RelicSystemConfig.RELIC_SUMMON_ANNOUNCE)
						{
							// Announce the new obtained relic.
							World.broadcastToAllOnlinePlayers(new ExRelicsAnnounce(_player, newRelic.getRelicId()));
						}
					}
					else // Add new relics if not A/B Grade relics.
					{
						storedRelics.add(newRelic);
						_player.storeRelics();
						updatedRelics.add(newRelic);
						if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
						{
							_player.sendMessage("2.New relic id: " + newRelic.getRelicId() + " was added to relic list.");
						}
						
						if (RelicSystemConfig.RELIC_SUMMON_ANNOUNCE && !RelicSystemConfig.RELIC_ANNOUNCE_ONLY_A_B_GRADE)
						{
							// Announce the new obtained relic
							World.broadcastToAllOnlinePlayers(new ExRelicsAnnounce(_player, newRelic.getRelicId()));
						}
						
						if (!_player.isRelicRegistered(newRelic.getRelicId(), newRelic.getRelicLevel()))
						{
							// Auto-Add to relic collections on summon.
							_player.sendPacket(new ExRelicsCollectionUpdate(_player, newRelic.getRelicId(), newRelic.getRelicLevel())); // Update collection list.
						}
					}
				}
				
				_player.storeRelics();
				// _player.giveRelicSkill(obtainedRelicTemplate);
			}
			else
			{
				PacketLogger.warning("ExRelicsSummonResult: Relic coupon " + _relicCouponItemId + " is probably not registred in configs.");
			}
		}
		
		if (!updatedRelics.isEmpty())
		{
			_player.sendPacket(new ExRelicsUpdateList(updatedRelics));
		}
		
		_player.sendPacket(new ExRelicsList(_player)); // Update confirmed relic list relics count.
		_player.sendPacket(new ExRelicsExchangeList(_player)); // Update relic exchange/confirm list.
	}
}
