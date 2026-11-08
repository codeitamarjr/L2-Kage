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
import org.l2jmobius.gameserver.entity.actor.Creature;
import org.l2jmobius.gameserver.mechanics.skill.BuffInfo;
import org.l2jmobius.gameserver.mechanics.skill.Skill;
import org.l2jmobius.gameserver.network.GameClient;
import org.l2jmobius.gameserver.network.ServerPackets;

/**
 * @author Mobius
 */
public class PartySpelled extends ServerPacket
{
	private final List<BuffInfo> _effects = new ArrayList<>();
	private final List<Skill> _effects2 = new ArrayList<>();
	private final Creature _creature;
	
	public PartySpelled(Creature creature)
	{
		_creature = creature;
	}
	
	public void addSkill(BuffInfo info)
	{
		if ((info != null) && info.isInUse())
		{
			_effects.add(info);
		}
	}
	
	public void addSkill(Skill skill)
	{
		if (skill != null)
		{
			_effects2.add(skill);
		}
	}
	
	@Override
	public void writeImpl(GameClient client, WriteBuffer buffer)
	{
		ServerPackets.PARTY_SPELLED.writeId(this, buffer);
		buffer.writeInt(_creature.isServitor() ? 2 : _creature.isPet() ? 1 : 0);
		buffer.writeInt(_creature.getObjectId());
		buffer.writeInt(_effects.size() + _effects2.size());
		for (BuffInfo info : _effects)
		{
			buffer.writeInt(info.getSkill().getDisplayId());
			buffer.writeShort(info.getSkill().getDisplayLevel());
			buffer.writeShort(0); // Sub level
			buffer.writeInt(info.getSkill().getAbnormalType().getClientId());
			writeOptionalInt(info.getTime(), buffer);
		}
		
		for (Skill skill : _effects2)
		{
			buffer.writeInt(skill.getDisplayId());
			buffer.writeShort(skill.getDisplayLevel());
			buffer.writeShort(0); // Sub level
			buffer.writeInt(skill.getAbnormalType().getClientId());
			buffer.writeShort(-1);
		}
	}
}
