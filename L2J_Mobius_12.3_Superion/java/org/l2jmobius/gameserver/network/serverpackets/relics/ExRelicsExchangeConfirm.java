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

import org.l2jmobius.commons.network.buffer.WriteBuffer;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.holders.player.PlayerRelicData;
import org.l2jmobius.gameserver.network.GameClient;
import org.l2jmobius.gameserver.network.ServerPackets;
import org.l2jmobius.gameserver.network.serverpackets.ServerPacket;

/**
 * @author CostyKiller
 */
public class ExRelicsExchangeConfirm extends ServerPacket
{
	private final Player _player;
	private final int _index;
	private final int _relicId;
	
	public ExRelicsExchangeConfirm(Player player, int index, int relicId)
	{
		_player = player;
		_index = index;
		_relicId = relicId;
	}
	
	@Override
	public void writeImpl(GameClient client, WriteBuffer buffer)
	{
		ServerPackets.EX_RELICS_EXCHANGE_CONFIRM.writeId(this, buffer);
		
		final PlayerRelicData pendingRelic = _player.getPendingRelic(_index, _relicId);
		final PlayerRelicData registeredRelic = pendingRelic != null ? _player.confirmPendingRelic(pendingRelic) : null;

		buffer.writeInt(_index);
		buffer.writeByte(registeredRelic != null);
		buffer.writeInt(_relicId);
		
		if (registeredRelic == null)
		{
			return;
		}
		
		_player.sendPacket(new ExRelicsUpdateList(1, registeredRelic.getRelicId(), registeredRelic.getRelicLevel(), registeredRelic.getRelicCount()));
		if (!_player.isRelicRegistered(registeredRelic.getRelicId(), registeredRelic.getRelicLevel()))
		{
			_player.sendPacket(new ExRelicsCollectionUpdate(_player, registeredRelic.getRelicId(), registeredRelic.getRelicLevel()));
		}
		_player.sendPacket(new ExRelicsList(_player));
		_player.sendPacket(new ExRelicsExchangeList(_player));
	}
}
