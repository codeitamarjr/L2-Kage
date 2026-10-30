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

import org.l2jmobius.gameserver.config.RelicSystemConfig;
import org.l2jmobius.gameserver.data.holders.RelicDataHolder;
import org.l2jmobius.gameserver.data.xml.RelicData;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.enums.player.RelicGrade;
import org.l2jmobius.gameserver.entity.actor.holders.player.PlayerRelicData;
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
import org.l2jmobius.gameserver.network.serverpackets.relics.ExRelicsUpdateList;

/**
 * Handles the confirmation signal from the client regarding relic combinations. This packet is unidirectional and does not require a ServerPacket response.
 * @author Galagard
 */
public class RequestRelicsConfirmCombination extends ClientPacket
{
	private int _grade;
	
	@Override
	protected void readImpl()
	{
		_grade = readInt(); // nGrade
	}
	
	@Override
	protected void runImpl()
	{
		final Player player = getPlayer();
		if (player == null)
		{
			return;
		}
		
		// final int maxPoints = RelicData.getInstance().getGuaranteedCompoundMaxPoints();
		final int pointsPerAttempt = RelicData.getInstance().getGuaranteedCompoundPointsPerAttempt();
		final String pityKey = (_grade == RelicGrade.CGRADE.ordinal()) ? PlayerVariables.RELICS_COMPOUNDING_POINTS_C_GRADE : PlayerVariables.RELICS_COMPOUNDING_POINTS_B_GRADE;
		final int currentPity = player.getVariables().getInt(pityKey, 0);
		
		// Validate if player has enough points for a guaranteed attempt.
		if (currentPity < pointsPerAttempt)
		{
			return;
		}
		
		final Collection<PlayerRelicData> storedRelics = player.getRelics();
		final int availableAttempts = currentPity / pointsPerAttempt;
		final long unconfirmedCount = storedRelics.stream().filter(relic -> (relic.getRelicIndex() >= 300) && (relic.getRelicCount() == 1)).count();
		if (availableAttempts > (RelicSystemConfig.RELIC_UNCONFIRMED_LIST_LIMIT - unconfirmedCount))
		{
			player.sendPacket(SystemMessageId.SUMMON_COMPOUND_IS_UNAVAILABLE_AS_YOU_HAVE_MORE_THAN_100_UNCONFIRMED_RELICS);
			return;
		}
		
		final ArrayList<Integer> successIds = new ArrayList<>();
		
		for (int i = 0; i < availableAttempts; i++)
		{
			final int obtainedRelicId = RelicData.getInstance().getGuaranteedRelicByCompound(RelicGrade.values()[_grade]);
			successIds.add(obtainedRelicId);
			
			// Guaranteed B/A rewards also require confirmation.
			final RelicDataHolder obtainedRelic = RelicData.getInstance().getRelic(obtainedRelicId);
			if ((obtainedRelic != null) && ((obtainedRelic.getGrade() == RelicGrade.BGRADE) || (obtainedRelic.getGrade() == RelicGrade.AGRADE)))
			{
				storedRelics.add(new PlayerRelicData(obtainedRelicId, 0, 1, player.getNextPendingRelicIndex(obtainedRelicId), System.currentTimeMillis()));
				player.getAccountVariables().set(AccountVariables.UNCONFIRMED_RELICS_COUNT, player.getAccountVariables().getInt(AccountVariables.UNCONFIRMED_RELICS_COUNT, 0) + 1);
				player.getAccountVariables().storeMe();
				player.sendPacket(new ExRelicsAnnounce(player, obtainedRelicId));
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
			
			if (existingRelic != null)
			{
				existingRelic.setRelicCount(existingRelic.getRelicCount() + 1);
				player.sendPacket(new ExRelicsUpdateList(1, existingRelic.getRelicId(), 0, existingRelic.getRelicCount() + 1));
				if (!player.isRelicRegistered(existingRelic.getRelicId(), existingRelic.getRelicLevel()))
				{
					player.sendPacket(new ExRelicsCollectionUpdate(player, existingRelic.getRelicId(), existingRelic.getRelicLevel()));
				}
			}
			else
			{
				final PlayerRelicData newRelic = new PlayerRelicData(obtainedRelicId, 0, 0, 0, 0);
				newRelic.setRelicIndex(0);
				storedRelics.add(newRelic);
				player.sendPacket(new ExRelicsUpdateList(1, newRelic.getRelicId(), 0, 0));
				if (!player.isRelicRegistered(newRelic.getRelicId(), newRelic.getRelicLevel()))
				{
					player.sendPacket(new ExRelicsCollectionUpdate(player, newRelic.getRelicId(), newRelic.getRelicLevel()));
				}
			}
			
			player.sendPacket(new ExRelicsAnnounce(player, obtainedRelicId));
		}
		
		// Send combination result with all guaranteed relics.
		player.sendPacket(new ExRelicsCombination(player, successIds, new ArrayList<>()));
		
		// Subtract all used points.
		player.getVariables().set(pityKey, currentPity - (availableAttempts * pointsPerAttempt));
		player.getVariables().storeMe();
		
		player.sendPacket(new ExRelicsPointInfo(player));
		player.sendPacket(new ExRelicsList(player));
		player.sendPacket(new ExRelicsExchangeList(player));
		player.storeRelics();
	}
}