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
import org.l2jmobius.gameserver.network.GameClient;
import org.l2jmobius.gameserver.network.ServerPackets;

public class PartySpelled extends ServerPacket
{
	private final List<BuffInfo> _effects = new ArrayList<>();
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
	
	@Override
	public void writeImpl(GameClient client, WriteBuffer buffer)
	{
		ServerPackets.PARTY_SPELLED.writeId(this, buffer);
		buffer.writeInt(_creature.isServitor() ? 2 : _creature.isPet() ? 1 : 0);
		buffer.writeInt(_creature.getObjectId());
		
		// C4 does not support more than 20 effects in party window, so limiting them makes no difference.
		// This check ignores first effects, so there is space for last effects to be viewable by party members.
		// It may also help healers be aware of cursed members.
		int size = 0;
		if (_effects.size() > 20)
		{
			buffer.writeInt(20);
			size = _effects.size() - 20;
		}
		else
		{
			buffer.writeInt(_effects.size());
		}
		
		for (; size < _effects.size(); size++)
		{
			final BuffInfo info = _effects.get(size);
			buffer.writeInt(info.getSkill().getDisplayId());
			buffer.writeShort(info.getSkill().getDisplayLevel());
			buffer.writeInt(info.getTime());
		}
	}
}
