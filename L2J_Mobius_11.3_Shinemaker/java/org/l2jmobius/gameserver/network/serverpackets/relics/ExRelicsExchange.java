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

import java.util.List;

import org.l2jmobius.commons.network.buffer.WriteBuffer;
import org.l2jmobius.commons.util.Rnd;
import org.l2jmobius.gameserver.config.RelicSystemConfig;
import org.l2jmobius.gameserver.data.xml.RelicData;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.holders.player.PlayerRelicData;
import org.l2jmobius.gameserver.entity.item.holders.ItemHolder;
import org.l2jmobius.gameserver.network.GameClient;
import org.l2jmobius.gameserver.network.ServerPackets;
import org.l2jmobius.gameserver.network.serverpackets.ServerPacket;

/**
 * @author CostyKiller
 */
public class ExRelicsExchange extends ServerPacket
{
	private final Player _player;
	private final int _index;
	private int _relicId;
	private boolean _success;
	
	public ExRelicsExchange(Player player, int index, int relicId)
	{
		_player = player;
		_index = index;
		_relicId = relicId;
		_success = false;
	}
	
	@Override
	public void writeImpl(GameClient client, WriteBuffer buffer)
	{
		ServerPackets.EX_RELICS_EXCHANGE.writeId(this, buffer);
		
		int attemptsRemaining = 0;
		int maxAttempts = 0;
		final PlayerRelicData pendingRelic = _player.getPendingRelic(_index, _relicId);
		if (pendingRelic != null)
		{
			int obtainedRelicId = 0;
			List<ItemHolder> feeList = null;
			switch (RelicData.getInstance().getRelic(_relicId).getGrade())
			{
				case 4:
				{
					maxAttempts = RelicSystemConfig.RELIC_REPLACE_ATTEMPTS_B_GRADE;
					feeList = RelicSystemConfig.RELIC_REPLACE_ATTEMPTS_FEES_B_GRADE;
					obtainedRelicId = getRandomRelicId(_relicId, Rnd.get(100) < RelicSystemConfig.RELIC_REPLACE_ATTEMPTS_CHANCE_SHINING_B_GRADE ? RelicSystemConfig.B_GRADE_SHINING_RELICS : RelicSystemConfig.B_GRADE_COMMON_RELICS);
					break;
				}
				case 5:
				{
					maxAttempts = RelicSystemConfig.RELIC_REPLACE_ATTEMPTS_A_GRADE;
					feeList = RelicSystemConfig.RELIC_REPLACE_ATTEMPTS_FEES_A_GRADE;
					obtainedRelicId = getRandomRelicId(_relicId, RelicSystemConfig.A_GRADE_COMMON_RELICS);
					break;
				}
			}
			
			// Attempts are counted per pending relic and the fee grows with each attempt used on it.
			final int usedAttempts = pendingRelic.getReplaceAttemptsUsed();
			attemptsRemaining = Math.max(0, maxAttempts - usedAttempts);
			if ((obtainedRelicId != 0) && (attemptsRemaining > 0) && (feeList != null) && !feeList.isEmpty())
			{
				final ItemHolder fee = feeList.get(Math.min(usedAttempts, feeList.size() - 1));
				if (_player.replacePendingRelic(pendingRelic, obtainedRelicId, fee, usedAttempts))
				{
					_relicId = obtainedRelicId;
					attemptsRemaining--;
					_success = true;
				}
				
				if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
				{
					_player.sendMessage("Replace fee item: " + fee.getId() + " x" + fee.getCount());
				}
			}
		}
		
		buffer.writeInt(_index);
		buffer.writeByte(_success);
		buffer.writeInt(attemptsRemaining); // Exchange attempts remaining.
		buffer.writeInt(maxAttempts); // Exchange attempts max.
		buffer.writeInt(_relicId);
		_player.sendPacket(new ExRelicsExchangeList(_player)); // Update relic exchange/confirm list.
	}
	
	private int getRandomRelicId(int currentRelicId, List<Integer> relicIds)
	{
		int result = currentRelicId;
		while (result == currentRelicId)
		{
			result = relicIds.get(Rnd.get(relicIds.size()));
		}
		
		return result;
	}
}
