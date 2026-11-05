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

import org.l2jmobius.gameserver.config.RelicSystemConfig;
import org.l2jmobius.gameserver.data.holders.RelicDataHolder;
import org.l2jmobius.gameserver.data.xml.RelicData;
import org.l2jmobius.gameserver.data.xml.SkillData;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.holders.player.PlayerRelicData;
import org.l2jmobius.gameserver.mechanics.skill.Skill;
import org.l2jmobius.gameserver.mechanics.variables.PlayerVariables;
import org.l2jmobius.gameserver.network.clientpackets.ClientPacket;
import org.l2jmobius.gameserver.network.serverpackets.relics.ExRelicsActiveInfo;

/**
 * @author CostyKiller
 */
public class RequestRelicsActive extends ClientPacket
{
	private int _relicId;
	
	@Override
	protected void readImpl()
	{
		_relicId = readInt();
	}
	
	@Override
	protected void runImpl()
	{
		final Player player = getPlayer();
		int relicLevel = -1;
		if (player == null)
		{
			return;
		}
		
		for (PlayerRelicData relic : player.getRelics())
		{
			if ((relic.getRelicId() == _relicId) && (relic.getRelicIndex() == 0))
			{
				relicLevel = relic.getRelicLevel();
				break;
			}
		}
		
		if ((relicLevel < 0) || (RelicData.getInstance().getRelic(_relicId) == null))
		{
			return;
		}
		
		final int skillId = RelicData.getInstance().getRelicSkillId(_relicId);
		final int skillLevel = relicLevel + 1;
		final Skill relicSkill = SkillData.getInstance().getSkill(skillId, skillLevel);
		if (relicSkill != null)
		{
			player.sendPacket(new ExRelicsActiveInfo(_relicId, relicLevel));
			player.getVariables().set(PlayerVariables.ACTIVE_RELIC, _relicId);
			player.getVariables().storeMe();
			
			// Remove previous active relic skill.
			for (RelicDataHolder relic : RelicData.getInstance().getRelics())
			{
				final Skill skill = player.getKnownSkill(relic.getSkillId());
				if (skill != null)
				{
					player.removeSkill(skill);
					if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
					{
						player.sendMessage("Relic Skill Id: " + skill.getId() + " Lvl: " + skill.getLevel() + " was removed.");
					}
				}
			}
			
			player.addSkill(relicSkill, true);
			if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
			{
				player.sendMessage("Relic Skill Id: " + skillId + " Lvl: " + skillLevel + " was added.");
			}
		}
		else
		{
			player.sendMessage("Relic skill does not exist!");
		}
	}
}
