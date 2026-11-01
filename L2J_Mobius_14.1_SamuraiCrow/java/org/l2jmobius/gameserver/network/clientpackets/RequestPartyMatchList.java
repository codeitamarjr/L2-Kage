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
package org.l2jmobius.gameserver.network.clientpackets;

import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.groups.matching.MatchingRoom;
import org.l2jmobius.gameserver.entity.groups.matching.MatchingRoomType;
import org.l2jmobius.gameserver.entity.groups.matching.PartyMatchingRoom;
import org.l2jmobius.gameserver.network.SystemMessageId;
import org.l2jmobius.gameserver.network.serverpackets.PartyRoomInfo;

/**
 * @author Gnacik
 */
public class RequestPartyMatchList extends ClientPacket
{
	private int _roomId;
	private int _maxMembers;
	private int _minLevel;
	private int _maxLevel;
	private int _lootType;
	private String _roomTitle;
	
	@Override
	protected void readImpl()
	{
		_roomId = readInt();
		_maxMembers = readInt();
		_minLevel = readInt();
		_maxLevel = readInt();
		_lootType = readInt();
		_roomTitle = readString();
	}
	
	@Override
	protected void runImpl()
	{
		final Player player = getPlayer();
		if (player == null)
		{
			return;
		}
		
		if ((_roomId <= 0) && (player.getMatchingRoom() == null))
		{
			if ((player.getLevel() < _minLevel) || (player.getLevel() > _maxLevel) || (_maxMembers < 1))
			{
				player.sendPacket(SystemMessageId.YOU_DO_NOT_MEET_THE_REQUIREMENTS_TO_ENTER_A_PARTY_ROOM);
				return;
			}
			
			new PartyMatchingRoom(_roomTitle, _lootType, _minLevel, _maxLevel, _maxMembers, player);
		}
		else
		{
			final MatchingRoom room = player.getMatchingRoom();
			if ((room.getId() == _roomId) && (room.getRoomType() == MatchingRoomType.PARTY) && room.isLeader(player))
			{
				room.setLootType(_lootType);
				room.setMinLevel(_minLevel);
				room.setMaxLevel(_maxLevel);
				room.setMaxMembers(_maxMembers);
				room.setTitle(_roomTitle);
				
				final PartyRoomInfo packet = new PartyRoomInfo((PartyMatchingRoom) room);
				for (Player member : room.getMembers())
				{
					member.sendPacket(packet);
				}
			}
		}
	}
}
