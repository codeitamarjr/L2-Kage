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
package org.l2jmobius.gameserver.network.serverpackets;

import java.util.ArrayList;
import java.util.List;

import org.l2jmobius.commons.network.buffer.WriteBuffer;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.Summon;
import org.l2jmobius.gameserver.entity.groups.Party;
import org.l2jmobius.gameserver.network.GameClient;
import org.l2jmobius.gameserver.network.ServerPackets;

/**
 * @author Mobius
 */
public class PartySmallWindowAll extends ServerPacket
{
	private final Party _party;
	private final Player _exclude;
	
	public PartySmallWindowAll(Player exclude, Party party)
	{
		_exclude = exclude;
		_party = party;
	}
	
	@Override
	public void writeImpl(GameClient client, WriteBuffer buffer)
	{
		ServerPackets.PARTY_SMALL_WINDOW_ALL.writeId(this, buffer);
		buffer.writeInt(_party.getLeaderObjectId());
		buffer.writeByte(_party.getDistributionType().getId());
		final List<Player> members = new ArrayList<>();
		for (Player member : _party.getMembers())
		{
			if ((member != null) && (member != _exclude))
			{
				members.add(member);
			}
		}
		
		buffer.writeByte(members.size());
		for (Player member : members)
		{
			buffer.writeInt(member.getObjectId());
			buffer.writeString(member.getAppearance().getVisibleName());
			buffer.writeInt((int) member.getCurrentCp()); // c4
			buffer.writeInt(member.getMaxCp()); // c4
			buffer.writeInt((int) member.getCurrentHp());
			buffer.writeInt(member.getMaxHp());
			buffer.writeInt((int) member.getCurrentMp());
			buffer.writeInt(member.getMaxMp());
			buffer.writeInt(member.getVitalityPoints());
			buffer.writeByte(member.getLevel());
			buffer.writeShort(member.getPlayerClass().getId());
			buffer.writeByte(1); // Unk
			buffer.writeShort(member.getRace().ordinal());
			buffer.writeInt(0); // 228
			final Summon pet = member.getPet();
			final List<Summon> servitors = new ArrayList<>(member.getServitors().values());
			buffer.writeInt(servitors.size() + (pet != null ? 1 : 0)); // Summon size, one only atm
			if (pet != null)
			{
				buffer.writeInt(pet.getObjectId());
				buffer.writeInt(pet.getId() + 1000000);
				buffer.writeByte(pet.getSummonType());
				buffer.writeString(pet.getName());
				buffer.writeInt((int) pet.getCurrentHp());
				buffer.writeInt(pet.getMaxHp());
				buffer.writeInt((int) pet.getCurrentMp());
				buffer.writeInt(pet.getMaxMp());
				buffer.writeByte(pet.getLevel());
			}
			
			for (Summon s : servitors)
			{
				buffer.writeInt(s.getObjectId());
				buffer.writeInt(s.getId() + 1000000);
				buffer.writeByte(s.getSummonType());
				buffer.writeString(s.getName());
				buffer.writeInt((int) s.getCurrentHp());
				buffer.writeInt(s.getMaxHp());
				buffer.writeInt((int) s.getCurrentMp());
				buffer.writeInt(s.getMaxMp());
				buffer.writeByte(s.getLevel());
			}
		}
	}
}
