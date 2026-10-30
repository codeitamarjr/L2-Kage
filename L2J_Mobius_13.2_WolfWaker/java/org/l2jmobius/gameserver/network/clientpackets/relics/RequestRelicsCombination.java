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
package org.l2jmobius.gameserver.network.clientpackets.relics;

import java.util.ArrayList;
import java.util.Collection;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Map.Entry;

import org.l2jmobius.commons.util.Rnd;
import org.l2jmobius.gameserver.config.RelicSystemConfig;
import org.l2jmobius.gameserver.data.holders.RelicCompoundFeeHolder;
import org.l2jmobius.gameserver.data.holders.RelicDataHolder;
import org.l2jmobius.gameserver.data.xml.RelicData;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.enums.player.RelicGrade;
import org.l2jmobius.gameserver.entity.actor.holders.player.PlayerRelicData;
import org.l2jmobius.gameserver.entity.item.enums.ItemProcessType;
import org.l2jmobius.gameserver.entity.item.holders.ItemHolder;
import org.l2jmobius.gameserver.mechanics.variables.AccountVariables;
import org.l2jmobius.gameserver.mechanics.variables.PlayerVariables;
import org.l2jmobius.gameserver.network.SystemMessageId;
import org.l2jmobius.gameserver.network.clientpackets.ClientPacket;
import org.l2jmobius.gameserver.network.serverpackets.relics.ExRelicsAnnounce;
import org.l2jmobius.gameserver.network.serverpackets.relics.ExRelicsCollectionUpdate;
import org.l2jmobius.gameserver.network.serverpackets.relics.ExRelicsCombination;
import org.l2jmobius.gameserver.network.serverpackets.relics.ExRelicsExchangeList;
import org.l2jmobius.gameserver.network.serverpackets.relics.ExRelicsList;
import org.l2jmobius.gameserver.network.serverpackets.relics.ExRelicsPointInfo;

/**
 * @author CostyKiller, Brado
 */
public class RequestRelicsCombination extends ClientPacket
{
	private int _relicsUsedGrade;
	private int _relicsUsedCount;
	private final List<Integer> _ingredientIds = new ArrayList<>();
	
	@Override
	protected void readImpl()
	{
		_relicsUsedGrade = readInt();
		_relicsUsedCount = readInt();
		while (remaining() > 0)
		{
			_ingredientIds.add(readInt());
		}
	}
	
	@Override
	protected void runImpl()
	{
		final Player player = getPlayer();
		if (player == null)
		{
			return;
		}
		
		if ((_relicsUsedGrade < 0) || (_relicsUsedGrade >= RelicGrade.values().length) || (_relicsUsedCount < 4) || ((_relicsUsedCount % 4) != 0) || (_ingredientIds.size() != _relicsUsedCount))
		{
			return;
		}
		
		final RelicGrade grade = RelicGrade.values()[_relicsUsedGrade];
		final RelicCompoundFeeHolder feeHolder = RelicData.getInstance().getCompoundFeeHolderByGrade(grade);
		if (feeHolder == null)
		{
			return;
		}
		
		final Collection<PlayerRelicData> storedRelics = player.getRelics();
		final Map<PlayerRelicData, Integer> ingredientCounts = new HashMap<>();
		for (int ingredientId : _ingredientIds)
		{
			final RelicDataHolder ingredientHolder = RelicData.getInstance().getRelic(ingredientId);
			if ((ingredientHolder == null) || (ingredientHolder.getGrade() != grade))
			{
				return;
			}
			
			final PlayerRelicData ingredientRelic = storedRelics.stream().filter(relic -> (relic.getRelicId() == ingredientId) && (relic.getRelicIndex() < 300)).findFirst().orElse(null);
			if (ingredientRelic == null)
			{
				return;
			}
			
			ingredientCounts.merge(ingredientRelic, 1, Integer::sum);
		}
		
		// Only spare copies can be used, the registered copy stays.
		for (Entry<PlayerRelicData, Integer> entry : ingredientCounts.entrySet())
		{
			if (entry.getKey().getRelicCount() < entry.getValue())
			{
				return;
			}
		}
		
		final List<Integer> unconfirmedRelics = new ArrayList<>();
		final int compoundAttempts = _relicsUsedCount / 4;
		for (PlayerRelicData relic : storedRelics)
		{
			if ((relic.getRelicIndex() >= 300) && (relic.getRelicCount() == 1)) // Unconfirmed relics are set on summon to index 300.
			{
				unconfirmedRelics.add(relic.getRelicId());
			}
		}
		
		if (unconfirmedRelics.size() >= RelicSystemConfig.RELIC_UNCONFIRMED_LIST_LIMIT) // If you have 100 relics in your confirmation list, restrictions are applied to the relic summoning and compounding functions.
		{
			player.sendPacket(SystemMessageId.SUMMON_COMPOUND_IS_UNAVAILABLE_AS_YOU_HAVE_MORE_THAN_100_UNCONFIRMED_RELICS);
			return;
		}
		// Reserve enough slots for a batch where every C/B-grade compound upgrades.
		if (((grade == RelicGrade.CGRADE) || (grade == RelicGrade.BGRADE)) && (compoundAttempts > (RelicSystemConfig.RELIC_UNCONFIRMED_LIST_LIMIT - unconfirmedRelics.size())))
		{
			player.sendPacket(SystemMessageId.SUMMON_COMPOUND_IS_UNAVAILABLE_AS_YOU_HAVE_MORE_THAN_100_UNCONFIRMED_RELICS);
			return;
		}
		
		if (!player.destroyItemByItemId(ItemProcessType.FEE, feeHolder.getId(), feeHolder.getCount() * compoundAttempts, player, true))
		{
			player.sendPacket(SystemMessageId.AUTO_COMPOUNDING_IS_CANCELLED_NOT_ENOUGH_MONEY);
			return;
		}
		
		if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
		{
			player.sendMessage("Compound Ingredients Grade: " + _relicsUsedGrade);
			player.sendMessage("Compound Ingredients: " + _relicsUsedCount);
			player.sendMessage("Compound Fee Holder ID: " + feeHolder.getId());
			player.sendMessage("Compound Fee Holder COUNT: " + feeHolder.getCount());
		}
		
		int earnedPityPoints = 0;
		int ingredientIndex = 0;
		for (int ingredientId : _ingredientIds)
		{
			ingredientIndex++;
			final PlayerRelicData ingredientRelic = storedRelics.stream().filter(relic -> (relic.getRelicId() == ingredientId) && (relic.getRelicIndex() < 300)).findFirst().orElse(null);
			if ((ingredientRelic != null) && (ingredientRelic.getRelicCount() > 0))
			{
				ingredientRelic.setRelicCount(ingredientRelic.getRelicCount() - 1);
				if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
				{
					player.sendMessage(String.format("Ingredient Relic %d data updated, ID: %d, Count: %d", ingredientIndex, ingredientRelic.getRelicId(), ingredientRelic.getRelicCount()));
				}
			}
		}
		
		final ArrayList<Integer> successCompoundIds = new ArrayList<>();
		final ArrayList<Integer> failCompoundIds = new ArrayList<>();
		for (int i = 0; i < compoundAttempts; i++)
		{
			final Entry<Boolean, Integer> result = RelicData.getInstance().getRelicByCompound(RelicGrade.values()[_relicsUsedGrade]);
			final int obtainedRelicId = result.getValue();
			if (result.getKey())
			{
				successCompoundIds.add(obtainedRelicId);
				player.sendPacket(new ExRelicsAnnounce(player, obtainedRelicId));
			}
			else
			{
				failCompoundIds.add(obtainedRelicId);
				player.sendPacket(new ExRelicsAnnounce(player, obtainedRelicId));
				
				// Give additional failure item if enabled and chance passes.
				if (RelicSystemConfig.RELIC_COMPOUND_ADDITIONAL_FAILURE_ITEM_ENABLED && (Rnd.get(100) < RelicSystemConfig.RELIC_COMPOUND_FAILURE_ITEM_CHANCE))
				{
					final List<ItemHolder> failureItems = (_relicsUsedGrade == RelicGrade.CGRADE.ordinal()) ? RelicSystemConfig.RELIC_COMPOUND_FAILURE_ITEM_C_GRADE : (_relicsUsedGrade == RelicGrade.BGRADE.ordinal()) ? RelicSystemConfig.RELIC_COMPOUND_FAILURE_ITEM_B_GRADE : null;
					if ((failureItems != null) && !failureItems.isEmpty())
					{
						for (ItemHolder item : failureItems)
						{
							player.addItem(ItemProcessType.REWARD, item.getId(), item.getCount(), player, true);
						}
					}
				}
			}
			
			// Earn a pity point only when a C/B compound fails to upgrade.
			final String pityKey = (_relicsUsedGrade == RelicGrade.CGRADE.ordinal()) ? PlayerVariables.RELICS_COMPOUNDING_POINTS_C_GRADE : (_relicsUsedGrade == RelicGrade.BGRADE.ordinal()) ? PlayerVariables.RELICS_COMPOUNDING_POINTS_B_GRADE : null;
			if ((pityKey != null) && !result.getKey())
			{
				final int currentPity = player.getVariables().getInt(pityKey, 0);
				if (currentPity < RelicData.getInstance().getGuaranteedCompoundMaxPoints())
				{
					player.getVariables().set(pityKey, currentPity + 1);
					earnedPityPoints++;
					player.getVariables().storeMe();
				}
			}
			
			// B/A compound rewards must be confirmed before registration.
			final RelicDataHolder obtainedRelic = RelicData.getInstance().getRelic(obtainedRelicId);
			if ((obtainedRelic != null) && ((obtainedRelic.getGrade() == RelicGrade.BGRADE) || (obtainedRelic.getGrade() == RelicGrade.AGRADE)))
			{
				storedRelics.add(new PlayerRelicData(obtainedRelicId, 0, 1, player.getNextPendingRelicIndex(obtainedRelicId), System.currentTimeMillis()));
				player.getAccountVariables().set(AccountVariables.UNCONFIRMED_RELICS_COUNT, player.getAccountVariables().getInt(AccountVariables.UNCONFIRMED_RELICS_COUNT, 0) + 1);
				player.getAccountVariables().storeMe();
				continue;
			}
			
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
			if (existingRelic != null)
			{
				existingRelic.setRelicCount(existingRelic.getRelicCount() + 1);
				if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
				{
					player.sendMessage("Existing relic id: " + obtainedRelicId + " count increased.");
				}
				
				if (existingRelic.getRelicIndex() == 0)
				{
					if (!player.isRelicRegistered(existingRelic.getRelicId(), existingRelic.getRelicLevel()))
					{
						player.sendPacket(new ExRelicsCollectionUpdate(player, existingRelic.getRelicId(), existingRelic.getRelicLevel())); // Update collection list.
					}
				}
			}
			else
			{
				newRelic.setRelicIndex(0);
				storedRelics.add(newRelic);
				if (newRelic.getRelicIndex() == 0)
				{
					if (!player.isRelicRegistered(newRelic.getRelicId(), newRelic.getRelicLevel()))
					{
						player.sendPacket(new ExRelicsCollectionUpdate(player, newRelic.getRelicId(), newRelic.getRelicLevel())); // Update collection list.
					}
				}
			}
		}
		
		final int successCount = successCompoundIds.size();
		final int failCount = failCompoundIds.size();
		if (failCount > successCount)
		{
			player.sendMessage("Relics compound has failed.");
		}
		
		player.sendMessage("You obtained through compounding: " + compoundAttempts + " relics.");
		player.sendMessage("Relics compound summary: " + successCompoundIds.size() + " succeded and " + failCompoundIds.size() + " failed.");
		
		// Show guaranteed compound progress for C and B grade only.
		final String pityKey = (_relicsUsedGrade == RelicGrade.CGRADE.ordinal()) ? PlayerVariables.RELICS_COMPOUNDING_POINTS_C_GRADE : (_relicsUsedGrade == RelicGrade.BGRADE.ordinal()) ? PlayerVariables.RELICS_COMPOUNDING_POINTS_B_GRADE : null;
		if (pityKey != null)
		{
			final int currentPity = player.getVariables().getInt(pityKey, 0);
			final int maxPoints = RelicData.getInstance().getGuaranteedCompoundMaxPoints();
			final int pointsPerAttempt = RelicData.getInstance().getGuaranteedCompoundPointsPerAttempt();
			final int attemptsAvailable = pointsPerAttempt > 0 ? currentPity / pointsPerAttempt : 0;
			player.sendMessage("Guaranteed compound points [" + RelicGrade.values()[_relicsUsedGrade] + "]: " + currentPity + "/" + maxPoints + " (" + earnedPityPoints + " added this session, " + attemptsAvailable + " guaranteed attempt(s) ready)");
		}
		player.sendPacket(new ExRelicsCombination(player, successCompoundIds, failCompoundIds));
		player.sendPacket(new ExRelicsList(player)); // Update confirmed relic list relics count.
		player.sendPacket(new ExRelicsExchangeList(player));
		player.sendPacket(new ExRelicsPointInfo(player));
		player.storeRelics();
	}
}