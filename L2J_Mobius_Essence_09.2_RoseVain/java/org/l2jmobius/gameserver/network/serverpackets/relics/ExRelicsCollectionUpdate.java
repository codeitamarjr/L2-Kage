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
import org.l2jmobius.gameserver.config.RelicSystemConfig;
import org.l2jmobius.gameserver.data.holders.RelicCollectionDataHolder;
import org.l2jmobius.gameserver.data.holders.RelicCollectionInfoHolder;
import org.l2jmobius.gameserver.data.xml.OptionData;
import org.l2jmobius.gameserver.data.xml.RelicCollectionData;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.holders.player.PlayerRelicCollectionData;
import org.l2jmobius.gameserver.entity.actor.holders.player.PlayerRelicData;
import org.l2jmobius.gameserver.mechanics.options.Options;
import org.l2jmobius.gameserver.network.GameClient;
import org.l2jmobius.gameserver.network.ServerPackets;
import org.l2jmobius.gameserver.network.serverpackets.ServerPacket;

/**
 * @author CostyKiller
 */
public class ExRelicsCollectionUpdate extends ServerPacket
{
	private final Player _player;
	private final int _relicId;
	private final int _relicLevel;
	
	public ExRelicsCollectionUpdate(Player player, int relicId, int relicLevel)
	{
		_player = player;
		_relicId = relicId;
		_relicLevel = relicLevel;
	}
	
	@Override
	public void writeImpl(GameClient client, WriteBuffer buffer)
	{
		ServerPackets.EX_RELICS_COLLECTION_UPDATE.writeId(this, buffer);
		
		final Collection<PlayerRelicData> storedRelics = _player.getRelics();
		PlayerRelicData existingRelic = null;
		
		// Check if the relic with the same ID exists.
		for (PlayerRelicData relic : storedRelics)
		{
			if ((relic.getRelicId() == _relicId) && (relic.getRelicIndex() == 0)) // Only relics with index 0 can be added to collection.
			{
				existingRelic = relic;
				break;
			}
		}
		
		// Check if obtained relic is required in some collection.
		final List<Integer> completedCollectionIds = new ArrayList<>();
		if (existingRelic != null)
		{
			final int relicId = existingRelic.getRelicId();
			int neededRelicCollectionId = 0;
			int neededRelicIndex = 0;
			int neededRelicLevel = 0;
			for (RelicCollectionDataHolder cRelicCollectionHolder : RelicCollectionData.getInstance().getRelicCollections())
			{
				// Find the relicId into collections.
				for (RelicCollectionInfoHolder relicData : cRelicCollectionHolder.getRelics())
				{
					// Relic id found.
					if ((relicData.getRelicId() == relicId) && (!_player.isRelicRegisteredInCollection(relicId, cRelicCollectionHolder.getCollectionId())))
					{
						for (int i = 0; i < cRelicCollectionHolder.getRelics().size(); i++)
						{
							final RelicCollectionInfoHolder relic = cRelicCollectionHolder.getRelic(i);
							if ((relic.getRelicId() == relicId))
							{
								neededRelicCollectionId = cRelicCollectionHolder.getCollectionId();
								neededRelicIndex = i; // Position found.
								neededRelicLevel = cRelicCollectionHolder.getRelic(i).getEnchantLevel();
								
								// Add relic to collection if matches the level needed.
								if (existingRelic.getRelicLevel() >= neededRelicLevel)
								{
									if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
									{
										_player.sendMessage("2.Relic id: " + existingRelic.getRelicId() + " with level: " + existingRelic.getRelicLevel() + " needed in collection: " + neededRelicCollectionId);
									}
									
									// Update Relic Collections from db.
									_player.getRelicCollections().add(new PlayerRelicCollectionData(neededRelicCollectionId, existingRelic.getRelicId(), neededRelicLevel, neededRelicIndex));
									_player.storeRelicCollections();
									_player.sendPacket(new ExRelicsCollectionInfo(_player));
									
									// Activate the reward when this relic completes the collection.
									if (_player.isCompleteRelicCollection(neededRelicCollectionId))
									{
										completedCollectionIds.add(neededRelicCollectionId);
										_player.sendPacket(new ExRelicsCollectionCompleteAnnounce(neededRelicCollectionId));
										
										final Options options = OptionData.getInstance().getOptions(cRelicCollectionHolder.getOptionId());
										if (options != null)
										{
											options.apply(_player);
											if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
											{
												_player.sendMessage("Added Skill for complete collection: " + options.getPassiveSkills());
											}
											
											_player.getCombatPower().updateRelicCollectionCombatPower();
											_player.sendCombatPower();
										}
									}
									
									if (RelicSystemConfig.RELIC_SYSTEM_DEBUG_ENABLED)
									{
										_player.sendMessage("Added Relic Id: " + existingRelic.getRelicId() + " into Collection Id: " + neededRelicCollectionId);
									}
								}
							}
						}
					}
				}
			}
		}
		
		buffer.writeInt(completedCollectionIds.size()); // Collection array size.
		for (int completedCollectionId : completedCollectionIds)
		{
			buffer.writeInt(completedCollectionId); // Collection id.
			buffer.writeByte(true); // Collection is complete.
			buffer.writeInt(1); // Registered relics in collection size.
			buffer.writeInt(1); // Array position.
			buffer.writeInt(_relicId); // Relic id.
			buffer.writeInt(_relicLevel); // Relic level.
		}
	}
}
